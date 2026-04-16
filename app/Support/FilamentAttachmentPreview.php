<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;

class FilamentAttachmentPreview
{
    /**
     * @return array{uid: string, count: int, files: array<int, array<string, mixed>>}
     */
    public static function payload(mixed $attachments, string $context = 'attachments'): array
    {
        $files = self::normalizeAttachmentItems($attachments);
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
                    'isSpreadsheet' => in_array($extension, ['xls', 'xlsx'], true),
                    'previewUrl' => $url,
                ];
            }, $files),
        ];
    }

    public static function render(mixed $attachments, string $context = 'attachments'): HtmlString
    {
        $payload = self::payload($attachments, $context);
        $files = $payload['files'];

        if ($files === []) {
            return new HtmlString(
                '<p style="font-size:0.875rem;color:#9ca3af;font-style:italic;">No files submitted.</p>'
            );
        }

        $uid = $payload['uid'];
        $fileData = $files;

        $sidebarHtml = collect($fileData)->map(function (array $file, int $index) use ($uid): string {
            $preview = $file['isImage']
                ? sprintf(
                    '<img src="%s" alt="%s" loading="lazy">',
                    e($file['url']),
                    e($file['name'])
                )
                : sprintf(
                    '<div class="%s__thumb-fallback">%s</div>',
                    $uid,
                    e(strtoupper($file['ext']))
                );

            return sprintf(
                '<button type="button" class="%1$s__thumb" x-on:click="selectFile(%2$d)" :class="{ \'is-active\': activeIndex === %2$d }">
                    <div class="%1$s__thumb-preview">%3$s</div>
                    <span class="%1$s__thumb-name" title="%4$s">%4$s</span>
                </button>',
                $uid,
                $index,
                $preview,
                e($file['name'])
            );
        })->implode('');

        $filesJson = json_encode($fileData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $count = $payload['count'];
        $filesJsonForScript = e($filesJson);

        $html = <<<HTML
    <div
        id="{$uid}"
        class="{$uid}"
        x-data="{
            files: [],
            activeIndex: 0,
            init() {
                this.files = JSON.parse(this.\$refs.filesData.textContent || '[]');
            },
            activeFile() {
                return this.files[this.activeIndex] ?? null;
            },
            selectFile(index) {
                if (index >= 0 && index < this.files.length) {
                    this.activeIndex = index;
                }
            },
            isPreviewable(file) {
                return !!file && (file.isImage || file.isPdf || file.isOffice);
            }
        }"
    >
    <script type="application/json" x-ref="filesData">{$filesJsonForScript}</script>
    <div class="{$uid}__header">
        <div>
            <p class="{$uid}__eyebrow">Attachments</p>
            <h4 class="{$uid}__title">Preview submitted files</h4>
        </div>
        <span class="{$uid}__count">{$count} file(s)</span>
    </div>

    <div class="{$uid}__layout">
        <div class="{$uid}__sidebar">
            {$sidebarHtml}
        </div>

        <div class="{$uid}__viewer">
            <div class="{$uid}__viewer-bar">
                <div>
                    <p class="{$uid}__file-label">Current file</p>
                    <p class="{$uid}__file-name" x-text="activeFile() ? activeFile().name : 'No file selected'"></p>
                </div>

                <div class="{$uid}__actions">
                    <a
                        :href="activeFile() ? activeFile().url : '#'"
                        target="_blank"
                        rel="noopener noreferrer"
                    >Open</a>
                    <a
                        :href="activeFile() ? activeFile().url : '#'"
                        :download="activeFile() ? activeFile().name : null"
                        target="_blank"
                        rel="noopener noreferrer"
                    >Download</a>
                </div>
            </div>

            <div class="{$uid}__preview">
                <template x-if="activeFile() && activeFile().isImage">
                    <img :src="activeFile().url" :alt="activeFile().name">
                </template>

                <template x-if="activeFile() && (activeFile().isPdf || activeFile().isOffice)">
                    <iframe :src="activeFile().previewUrl || activeFile().url" :title="activeFile().name"></iframe>
                </template>

                <template x-if="activeFile() && !isPreviewable(activeFile())">
                    <div class="{$uid}__fallback">
                        <div class="{$uid}__fallback-type" x-text="String(activeFile().ext || 'file').toUpperCase()"></div>
                        <p>Inline preview is not available for this file type.</p>
                        <p x-text="'Use Open or Download to inspect ' + activeFile().name + '.'"></p>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<style>
    #{$uid} {
        border: 1px solid #dbe2ea;
        border-radius: 16px;
        background: linear-gradient(180deg, #f8fbff 0%, #eef4fb 100%);
        overflow: hidden;
    }

    #{$uid} .{$uid}__header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 16px 18px;
        border-bottom: 1px solid #dbe2ea;
        background: rgba(255, 255, 255, 0.85);
    }

    #{$uid} .{$uid}__eyebrow {
        margin: 0 0 4px;
        font-size: 11px;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #64748b;
        font-weight: 700;
    }

    #{$uid} .{$uid}__title {
        margin: 0;
        font-size: 15px;
        font-weight: 700;
        color: #0f172a;
    }

    #{$uid} .{$uid}__count {
        display: inline-flex;
        align-items: center;
        padding: 6px 10px;
        border-radius: 999px;
        background: #dbeafe;
        color: #1d4ed8;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }

    #{$uid} .{$uid}__layout {
        display: grid;
        grid-template-columns: minmax(180px, 220px) minmax(0, 1fr);
        min-height: 420px;
    }

    #{$uid} .{$uid}__sidebar {
        padding: 14px;
        border-right: 1px solid #dbe2ea;
        background: rgba(255, 255, 255, 0.82);
        display: flex;
        flex-direction: column;
        gap: 10px;
        max-height: 560px;
        overflow-y: auto;
    }

    #{$uid} .{$uid}__thumb {
        width: 100%;
        border: 1px solid #dbe2ea;
        border-radius: 12px;
        padding: 10px;
        background: #ffffff;
        text-align: left;
        cursor: pointer;
        transition: border-color 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
    }

    #{$uid} .{$uid}__thumb:hover,
    #{$uid} .{$uid}__thumb.is-active {
        border-color: #60a5fa;
        box-shadow: 0 10px 24px rgba(37, 99, 235, 0.12);
        transform: translateY(-1px);
    }

    #{$uid} .{$uid}__thumb-preview {
        height: 96px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border-radius: 10px;
        background: #f8fafc;
    }

    #{$uid} .{$uid}__thumb-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    #{$uid} .{$uid}__thumb-fallback {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 64px;
        min-height: 64px;
        border-radius: 12px;
        padding: 12px;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: 14px;
        font-weight: 700;
        letter-spacing: 0.06em;
    }

    #{$uid} .{$uid}__thumb-name {
        display: block;
        margin-top: 10px;
        font-size: 12px;
        color: #334155;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    #{$uid} .{$uid}__viewer {
        min-width: 0;
        display: flex;
        flex-direction: column;
    }

    #{$uid} .{$uid}__viewer-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 16px 18px;
        border-bottom: 1px solid #dbe2ea;
        background: rgba(255, 255, 255, 0.7);
    }

    #{$uid} .{$uid}__file-label {
        margin: 0 0 4px;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #64748b;
        font-weight: 700;
    }

    #{$uid} .{$uid}__file-name {
        margin: 0;
        font-size: 14px;
        font-weight: 600;
        color: #0f172a;
        word-break: break-word;
    }

    #{$uid} .{$uid}__actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    #{$uid} .{$uid}__actions a {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 8px 12px;
        border-radius: 10px;
        border: 1px solid #bfdbfe;
        background: #eff6ff;
        color: #1d4ed8;
        text-decoration: none;
        font-size: 12px;
        font-weight: 700;
    }

    #{$uid} .{$uid}__preview {
        flex: 1;
        min-height: 320px;
        padding: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        background:
            radial-gradient(circle at top right, rgba(191, 219, 254, 0.75), transparent 35%),
            linear-gradient(180deg, #f8fafc 0%, #e2e8f0 100%);
    }

    #{$uid} .{$uid}__preview img,
    #{$uid} .{$uid}__preview iframe {
        width: 100%;
        max-width: 100%;
        min-height: 460px;
        border: 0;
        border-radius: 14px;
        background: #ffffff;
        box-shadow: 0 18px 36px rgba(15, 23, 42, 0.12);
    }

    #{$uid} .{$uid}__preview img {
        object-fit: contain;
        min-height: auto;
        max-height: 70vh;
        padding: 16px;
    }

    #{$uid} .{$uid}__fallback {
        max-width: 420px;
        padding: 28px;
        border: 1px dashed #94a3b8;
        border-radius: 18px;
        text-align: center;
        background: rgba(255, 255, 255, 0.9);
        color: #334155;
    }

    #{$uid} .{$uid}__fallback-type {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 68px;
        height: 68px;
        margin-bottom: 14px;
        border-radius: 20px;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: 18px;
        font-weight: 700;
        letter-spacing: 0.06em;
    }

    #{$uid} .{$uid}__fallback p {
        margin: 0 0 8px;
        font-size: 13px;
    }

    @media (max-width: 960px) {
        #{$uid} .{$uid}__layout {
            grid-template-columns: 1fr;
        }

        #{$uid} .{$uid}__sidebar {
            border-right: 0;
            border-bottom: 1px solid #dbe2ea;
        }

        #{$uid} .{$uid}__viewer-bar {
            flex-direction: column;
            align-items: flex-start;
        }

        #{$uid} .{$uid}__preview {
            padding: 12px;
        }
    }
</style>

HTML;

        return new HtmlString($html);
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

        if (!is_array($attachments)) {
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

            if (!is_array($attachment)) {
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
}
