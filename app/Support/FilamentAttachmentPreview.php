<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;

class FilamentAttachmentPreview
{
    /**
     * @return array{
     *     uid: string,
     *     count: int,
     *     files: array<int, array<string, mixed>>,
     *     threads: array<string, array<int, array<string, string|null>>>,
     *     drafts: array<string, string>,
     *     annotations: array<string, array<int, array<string, mixed>>>,
     *     viewStates: array<string, array<string, mixed>>,
     *     viewerType: string|null
     * }
     */
    public static function payload(
        mixed $attachments,
        string $context = 'attachments',
        mixed $remarks = [],
        mixed $drafts = [],
        ?string $viewerType = null,
        mixed $annotations = [],
        mixed $viewStates = []
    ): array {
        $files = self::normalizeAttachmentItems($attachments);
        $normalizedThreads = self::normalizeRemarkThreads($remarks);
        $normalizedDrafts = self::normalizeDrafts($drafts);
        $normalizedAnnotations = self::normalizeAnnotations($annotations);
        $normalizedViewStates = self::normalizeViewStates($viewStates);
        $uid = sprintf(
            'attachment_preview_%s_%s',
            preg_replace('/[^A-Za-z0-9_-]/', '_', $context) ?: 'files',
            substr(md5($context . json_encode($files)), 0, 8)
        );

        return [
            'uid' => $uid,
            'count' => count($files),
            'files' => array_map(function (array $file) use ($uid): array {
                $path = $file['path'];
                $url = $file['url'];
                $name = $file['name'];
                $extension = strtolower($file['ext'] ?: pathinfo($name ?: $path, PATHINFO_EXTENSION));
                $isOffice = in_array($extension, ['doc', 'docx', 'xls', 'xlsx'], true);

                return [
                    'path' => $path,
                    'url' => $url,
                    'name' => $name,
                    'ext' => $extension ?: 'file',
                    'uidClass' => $uid,
                    'isImage' => in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true),
                    'isPdf' => $extension === 'pdf',
                    'isOffice' => $isOffice,
                    'isDocx' => $extension === 'docx',
                    'isSpreadsheet' => in_array($extension, ['xls', 'xlsx', 'csv'], true),
                    'previewUrl' => $url,
                    'contentPreviewUrl' => AttachmentContentPreview::supports($path)
                        ? route('attachments.preview', ['path' => $path])
                        : null,
                ];
            }, $files),
            'threads' => self::filterThreadsToFiles($normalizedThreads, $files),
            'drafts' => self::filterDraftsToFiles($normalizedDrafts, $files),
            'annotations' => self::filterAnnotationsToFiles($normalizedAnnotations, $files),
            'viewStates' => self::filterViewStatesToFiles($normalizedViewStates, $files),
            'viewerType' => $viewerType,
        ];
    }

    public static function render(
        mixed $attachments,
        string $context = 'attachments',
        mixed $remarks = [],
        mixed $annotations = []
    ): HtmlString {
        return new HtmlString((string) view('filament.forms.components.attachment-preview', [
            'preview' => self::payload($attachments, $context, $remarks, [], null, $annotations, []),
            'editable' => false,
            'annotationEditable' => false,
            'draftsStatePath' => null,
            'annotationsStatePath' => null,
            'viewStatesStatePath' => null,
        ]));
    }

    /**
     * @return array<string, array<int, array<string, string|null>>>
     */
    public static function mergeRemarkThreads(
        mixed $existingRemarks,
        mixed $drafts,
        ?string $authorName = null,
        ?string $authorLabel = null,
        ?string $authorType = null,
        ?string $createdAt = null
    ): array {
        $threads = self::normalizeRemarkThreads($existingRemarks);
        $drafts = self::normalizeDrafts($drafts);
        $createdAt ??= now()->toDateTimeString();

        foreach ($drafts as $path => $draft) {
            $message = trim($draft);

            if ($message === '') {
                continue;
            }

            $threads[$path] ??= [];
            $threads[$path][] = [
                'message' => $message,
                'author_name' => $authorName,
                'author_label' => $authorLabel,
                'author_type' => $authorType,
                'created_at' => $createdAt,
            ];
        }

        return $threads;
    }

    /**
     * @return array<int, array{path: string, url: string, name: string, ext: string}>
     */
    protected static function normalizeAttachmentItems(mixed $attachments): array
    {
        if (is_string($attachments)) {
            $decoded = json_decode($attachments, true);
            $attachments = is_array($decoded) ? $decoded : [$attachments];
        }

        if (! is_array($attachments)) {
            return [];
        }

        $normalized = [];

        foreach ($attachments as $attachment) {
            if (is_string($attachment) && $attachment !== '') {
                $normalized[] = [
                    'path' => $attachment,
                    'url' => self::resolveUrl($attachment),
                    'name' => basename($attachment),
                    'ext' => strtolower(pathinfo($attachment, PATHINFO_EXTENSION)),
                ];

                continue;
            }

            if (! is_array($attachment)) {
                continue;
            }

            $path = (string) ($attachment['path'] ?? $attachment['file'] ?? $attachment['url'] ?? '');

            if ($path === '') {
                continue;
            }

            $name = (string) ($attachment['name'] ?? basename($path));
            $url = (string) ($attachment['url'] ?? self::resolveUrl($path));
            $ext = (string) ($attachment['ext'] ?? pathinfo($name ?: $path, PATHINFO_EXTENSION));

            $normalized[] = [
                'path' => $path,
                'url' => filter_var($url, FILTER_VALIDATE_URL) ? $url : self::resolveUrl($url),
                'name' => $name,
                'ext' => strtolower($ext),
            ];
        }

        return array_values($normalized);
    }

    /**
     * @return array<string, array<int, array<string, string|null>>>
     */
    protected static function normalizeRemarkThreads(mixed $remarks): array
    {
        if (is_string($remarks)) {
            $decoded = json_decode($remarks, true);
            $remarks = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($remarks)) {
            return [];
        }

        return collect($remarks)
            ->filter(fn (mixed $value, mixed $key): bool => is_string($key) && $key !== '')
            ->map(fn (mixed $value): array => self::normalizeThreadEntries($value))
            ->all();
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    protected static function normalizeThreadEntries(mixed $value): array
    {
        if (is_scalar($value)) {
            $message = trim((string) $value);

            return $message === ''
                ? []
                : [[
                    'message' => $message,
                    'author_name' => null,
                    'author_label' => 'Comment',
                    'author_type' => null,
                    'created_at' => null,
                ]];
        }

        if (! is_array($value)) {
            return [];
        }

        if (array_key_exists('message', $value) || array_key_exists('body', $value)) {
            $message = trim((string) ($value['message'] ?? $value['body'] ?? ''));

            return $message === ''
                ? []
                : [[
                    'message' => $message,
                    'author_name' => self::nullableString($value['author_name'] ?? null),
                    'author_label' => self::nullableString($value['author_label'] ?? $value['author_role'] ?? 'Comment'),
                    'author_type' => self::resolveAuthorType($value),
                    'created_at' => self::nullableString($value['created_at'] ?? null),
                ]];
        }

        return collect($value)
            ->map(function (mixed $entry): ?array {
                if (is_scalar($entry)) {
                    $message = trim((string) $entry);

                    return $message === ''
                        ? null
                        : [
                            'message' => $message,
                            'author_name' => null,
                            'author_label' => 'Comment',
                            'author_type' => null,
                            'created_at' => null,
                        ];
                }

                if (! is_array($entry)) {
                    return null;
                }

                $message = trim((string) ($entry['message'] ?? $entry['body'] ?? ''));

                if ($message === '') {
                    return null;
                }

                return [
                    'message' => $message,
                    'author_name' => self::nullableString($entry['author_name'] ?? null),
                    'author_label' => self::nullableString($entry['author_label'] ?? $entry['author_role'] ?? 'Comment'),
                    'author_type' => self::resolveAuthorType($entry),
                    'created_at' => self::nullableString($entry['created_at'] ?? null),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected static function normalizeDrafts(mixed $drafts): array
    {
        if (is_string($drafts)) {
            $decoded = json_decode($drafts, true);
            $drafts = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($drafts)) {
            return [];
        }

        return collect($drafts)
            ->filter(fn (mixed $value, mixed $key): bool => is_string($key) && $key !== '')
            ->map(fn (mixed $value): string => is_scalar($value) ? (string) $value : '')
            ->all();
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected static function normalizeAnnotations(mixed $annotations): array
    {
        if (is_string($annotations)) {
            $decoded = json_decode($annotations, true);
            $annotations = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($annotations)) {
            return [];
        }

        return collect($annotations)
            ->filter(fn (mixed $value, mixed $key): bool => is_string($key) && $key !== '')
            ->map(function (mixed $entries): array {
                if (! is_array($entries)) {
                    return [];
                }

                return collect($entries)
                    ->map(function (mixed $entry): ?array {
                        if (! is_array($entry)) {
                            return null;
                        }

                        $text = trim((string) ($entry['text'] ?? $entry['message'] ?? ''));

                        if ($text === '') {
                            return null;
                        }

                        return [
                            'id' => self::nullableString($entry['id'] ?? null) ?: (string) str()->uuid(),
                            'text' => $text,
                            'x' => round(max(0, min(100, (float) ($entry['x'] ?? 50))), 2),
                            'y' => round(max(0, min(100, (float) ($entry['y'] ?? 50))), 2),
                            'page' => max(1, (int) ($entry['page'] ?? 1)),
                            'color' => self::nullableString($entry['color'] ?? null) ?: '#f97316',
                            'author_name' => self::nullableString($entry['author_name'] ?? null),
                            'author_label' => self::nullableString($entry['author_label'] ?? 'Annotation'),
                            'author_type' => self::resolveAuthorType($entry),
                            'created_at' => self::nullableString($entry['created_at'] ?? null),
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();
            })
            ->all();
    }

    /**
     * @return array<string, array{rotation:int}>
     */
    protected static function normalizeViewStates(mixed $viewStates): array
    {
        if (is_string($viewStates)) {
            $decoded = json_decode($viewStates, true);
            $viewStates = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($viewStates)) {
            return [];
        }

        return collect($viewStates)
            ->filter(fn (mixed $value, mixed $key): bool => is_string($key) && $key !== '')
            ->map(function (mixed $entry): array {
                if (! is_array($entry)) {
                    return ['rotation' => 0];
                }

                return [
                    'rotation' => self::normalizeRotation($entry['rotation'] ?? 0),
                ];
            })
            ->all();
    }

    /**
     * @param  array<int, array{path: string, url: string, name: string, ext: string}>  $files
     * @return array<string, array<int, array<string, string|null>>>
     */
    protected static function filterThreadsToFiles(array $threads, array $files): array
    {
        $validPaths = self::collectFilePaths($files);

        return collect($validPaths)
            ->mapWithKeys(fn (string $path): array => [$path => $threads[$path] ?? []])
            ->all();
    }

    /**
     * @param  array<int, array{path: string, url: string, name: string, ext: string}>  $files
     * @return array<string, string>
     */
    protected static function filterDraftsToFiles(array $drafts, array $files): array
    {
        $validPaths = self::collectFilePaths($files);

        return collect($validPaths)
            ->mapWithKeys(fn (string $path): array => [$path => $drafts[$path] ?? ''])
            ->all();
    }

    /**
     * @param  array<int, array{path: string, url: string, name: string, ext: string}>  $files
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected static function filterAnnotationsToFiles(array $annotations, array $files): array
    {
        $validPaths = self::collectFilePaths($files);

        return collect($validPaths)
            ->mapWithKeys(fn (string $path): array => [$path => array_values($annotations[$path] ?? [])])
            ->all();
    }

    /**
     * @param  array<int, array{path: string, url: string, name: string, ext: string}>  $files
     * @return array<string, array{rotation:int}>
     */
    public static function filterViewStatesToFiles(mixed $viewStates, array $files): array
    {
        $normalized = self::normalizeViewStates($viewStates);
        $validPaths = self::collectFilePaths($files);

        return collect($validPaths)
            ->mapWithKeys(fn (string $path): array => [$path => $normalized[$path] ?? ['rotation' => 0]])
            ->all();
    }

    /**
     * @param  array<int, array{path: string, url: string, name: string, ext: string}>  $files
     * @return Collection<int, string>
     */
    protected static function collectFilePaths(array $files): Collection
    {
        return collect($files)
            ->pluck('path')
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->values();
    }

    protected static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected static function resolveAuthorType(array $entry): ?string
    {
        $explicit = self::nullableString($entry['author_type'] ?? null);

        if ($explicit !== null) {
            return $explicit;
        }

        $label = strtolower((string) ($entry['author_label'] ?? $entry['author_role'] ?? ''));

        return match ($label) {
            'complying office' => 'complying_office',
            'requiring agency' => 'requiring_agency',
            'super admin' => 'super_admin',
            'user' => 'user',
            default => null,
        };
    }

    protected static function resolveUrl(string $path): string
    {
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        $url = Storage::disk('public')->url($path);

        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        return URL::to($url);
    }

    protected static function normalizeRotation(mixed $value): int
    {
        $rotation = (int) $value;
        $normalized = (($rotation % 360) + 360) % 360;

        return match ($normalized) {
            90, 180, 270 => $normalized,
            default => 0,
        };
    }
}
