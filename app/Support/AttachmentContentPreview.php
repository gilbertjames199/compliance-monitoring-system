<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\CSV\Reader as CsvReader;

class AttachmentContentPreview
{
    private const MAX_SHEETS = 6;
    private const MAX_ROWS_PER_SHEET = 100;
    private const MAX_COLUMNS = 20;
    private const MAX_TEXT_BYTES = 20000;

    public static function supports(string $path): bool
    {
        return in_array(self::extension($path), [
            'csv',
            'json',
            'log',
            'md',
            'text',
            'txt',
            'xml',
            'yml',
            'yaml',
        ], true);
    }

    public static function render(string $path): string
    {
        $extension = self::extension($path);
        $absolutePath = Storage::disk('public')->path($path);

        return match ($extension) {
            'csv' => self::renderCsv($absolutePath),
            'json', 'log', 'md', 'text', 'txt', 'xml', 'yml', 'yaml' => self::renderTextFile($absolutePath),
            default => throw new \RuntimeException('Inline preview is not available for this file type.'),
        };
    }

    private static function renderCsv(string $absolutePath): string
    {
        $reader = new CsvReader();
        $reader->open($absolutePath);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $rows = [];
                $rowCount = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $rowCount++;

                    if ($rowCount > self::MAX_ROWS_PER_SHEET) {
                        $rows[] = '<tr><td colspan="' . self::MAX_COLUMNS . '" class="attachment-preview__truncated">More rows are available in the downloaded file.</td></tr>';
                        break;
                    }

                    $values = array_slice($row->toArray(), 0, self::MAX_COLUMNS);

                    $cells = [
                        '<th scope="row" class="attachment-preview__sheet-index">' . $rowCount . '</th>',
                    ];

                    foreach ($values as $columnIndex => $value) {
                        $cells[] = sprintf(
                            '<td data-column="%s">%s</td>',
                            self::columnLabel($columnIndex),
                            self::escape(self::stringify($value))
                        );
                    }

                    $rows[] = '<tr>' . implode('', $cells) . '</tr>';
                }

                if ($rows === []) {
                    return '<p>No rows found in this CSV file.</p>';
                }

                return sprintf(
                    '<section class="attachment-preview__sheet"><h4>%s</h4><div class="attachment-preview__sheet-table"><table><thead>%s</thead><tbody>%s</tbody></table></div></section>',
                    self::escape(basename($absolutePath)),
                    self::buildSpreadsheetHead($rows),
                    implode('', $rows)
                );
            }
        } finally {
            $reader->close();
        }

        return '<p>No rows found in this CSV file.</p>';
    }

    private static function renderTextFile(string $absolutePath): string
    {
        $content = file_get_contents($absolutePath);

        if ($content === false) {
            throw new \RuntimeException('Unable to read this file.');
        }

        $truncated = false;

        if (strlen($content) > self::MAX_TEXT_BYTES) {
            $content = substr($content, 0, self::MAX_TEXT_BYTES);
            $truncated = true;
        }

        $html = '<pre class="attachment-preview__text-file">' . self::escape($content) . '</pre>';

        if ($truncated) {
            $html .= self::renderLimitNotice('Only the first part of this file is shown inline.');
        }

        return $html;
    }

    private static function renderLimitNotice(string $message): string
    {
        return '<p class="attachment-preview__limit-note">' . self::escape($message) . '</p>';
    }

    private static function stringify(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private static function extension(string $path): string
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

}
