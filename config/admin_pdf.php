<?php
declare(strict_types=1);

final class AdminPdfDocument
{
    private const WIDTH = 595.0;
    private const HEIGHT = 842.0;
    private const MARGIN = 42.0;

    /** @var list<string> */
    private array $pages = [];
    private float $y = 105.0;

    public function __construct()
    {
        $this->addPage(false);
    }

    public function heading(string $generatedAt): void
    {
        $this->text(42, 113, 22, 'Administration report', true, '0.028 0.118 0.165');
        $this->text(42, 133, 9, 'Generated ' . $generatedAt, false, '0.36 0.46 0.50');
        $this->y = 158;
    }

    /** @param array<string,int> $stats */
    public function summary(array $stats): void
    {
        $items = [
            ['Active users', (string)$stats['users']],
            ['Approved businesses', (string)$stats['businesses']],
            ['Pending review', (string)$stats['pending']],
            ['Translations', (string)$stats['translations']],
        ];
        $gap = 8.0;
        $width = (self::WIDTH - (self::MARGIN * 2) - ($gap * 3)) / 4;
        foreach ($items as $index => [$label, $value]) {
            $x = self::MARGIN + (($width + $gap) * $index);
            $this->rect($x, $this->y, $width, 60, '0.965 0.985 0.987');
            $this->text($x + 12, $this->y + 26, 18, $value, true, '0.031 0.490 0.427');
            $this->text($x + 12, $this->y + 45, 8, $label, false, '0.25 0.35 0.39');
        }
        $this->y += 82;
    }

    public function section(string $title): void
    {
        $this->ensureSpace(38);
        $this->text(self::MARGIN, $this->y + 13, 13, $title, true, '0.028 0.118 0.165');
        $this->line(self::MARGIN, $this->y + 22, self::WIDTH - self::MARGIN, $this->y + 22, '0.05 0.68 0.60', 1.2);
        $this->y += 32;
    }

    /** @param list<string> $headers @param list<list<string|int|float|null>> $rows @param list<float> $widths */
    public function table(array $headers, array $rows, array $widths): void
    {
        if ($rows === []) {
            $this->ensureSpace(30);
            $this->text(self::MARGIN + 10, $this->y + 18, 9, 'No records available.', false, '0.36 0.46 0.50');
            $this->y += 30;
            return;
        }

        $drawHeader = function () use ($headers, $widths): void {
            $this->ensureSpace(48);
            $this->rect(self::MARGIN, $this->y, array_sum($widths), 24, '0.890 0.970 0.960');
            $x = self::MARGIN;
            foreach ($headers as $index => $header) {
                $this->text($x + 7, $this->y + 16, 7.5, strtoupper($header), true, '0.031 0.365 0.337');
                $x += $widths[$index];
            }
            $this->y += 24;
        };

        $drawHeader();
        foreach ($rows as $row) {
            if ($this->y + 25 > 790) {
                $this->addPage(true);
                $drawHeader();
            }
            $this->line(self::MARGIN, $this->y + 24, self::MARGIN + array_sum($widths), $this->y + 24, '0.86 0.91 0.92', .6);
            $x = self::MARGIN;
            foreach ($widths as $index => $width) {
                $maxCharacters = max(5, (int)floor(($width - 14) / 4.6));
                $value = $this->shorten((string)($row[$index] ?? ''), $maxCharacters);
                $this->text($x + 7, $this->y + 16, 8.5, $value, false, '0.10 0.20 0.24');
                $x += $width;
            }
            $this->y += 24;
        }
        $this->y += 10;
    }

    public function output(): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        $kids = [];
        foreach ($this->pages as $index => $commands) {
            $pageId = 5 + ($index * 2);
            $contentId = $pageId + 1;
            $kids[] = $pageId . ' 0 R';
            $footer = $this->textCommand(42, 817, 8, 'TourLingo administration report', false, '0.42 0.50 0.53');
            $footer .= $this->textCommand(515, 817, 8, 'Page ' . ($index + 1), false, '0.42 0.50 0.53');
            $stream = $commands . $footer;
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
            $objects[$contentId] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($id = 1; $id <= count($objects); $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        return $pdf;
    }

    private function addPage(bool $continued): void
    {
        $this->pages[] = '';
        if ($continued) {
            $this->rect(0, 0, self::WIDTH, 6, '0.05 0.68 0.60');
            $this->text(42, 36, 14, 'Administration report - continued', true, '0.028 0.118 0.165');
            $this->y = 58;
            return;
        }
        $this->y = 105;
        $this->rect(0, 0, self::WIDTH, 78, '0.031 0.184 0.286');
        $this->text(42, 38, 20, 'TourLingo', true, '1 1 1');
        $this->text(42, 57, 9, 'Platform operations', false, '0.72 0.90 0.92');
    }

    private function ensureSpace(float $height): void
    {
        if ($this->y + $height > 790) {
            $this->addPage(true);
        }
    }

    private function rect(float $x, float $top, float $width, float $height, string $fill): void
    {
        $bottom = self::HEIGHT - $top - $height;
        $this->append("q {$fill} rg {$x} {$bottom} {$width} {$height} re f Q\n");
    }

    private function line(float $x1, float $top1, float $x2, float $top2, string $stroke, float $width): void
    {
        $y1 = self::HEIGHT - $top1;
        $y2 = self::HEIGHT - $top2;
        $this->append("q {$stroke} RG {$width} w {$x1} {$y1} m {$x2} {$y2} l S Q\n");
    }

    private function text(float $x, float $top, float $size, string $value, bool $bold, string $fill): void
    {
        $this->append($this->textCommand($x, $top, $size, $value, $bold, $fill));
    }

    private function textCommand(float $x, float $top, float $size, string $value, bool $bold, string $fill): string
    {
        $font = $bold ? 'F2' : 'F1';
        $baseline = self::HEIGHT - $top;
        return "BT /{$font} {$size} Tf {$fill} rg 1 0 0 1 {$x} {$baseline} Tm (" . $this->escape($value) . ") Tj ET\n";
    }

    private function append(string $command): void
    {
        $index = array_key_last($this->pages);
        $this->pages[$index] .= $command;
    }

    private function escape(string $value): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
        $safe = $encoded === false ? $value : $encoded;
        $safe = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $safe) ?? '';
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $safe);
    }

    private function shorten(string $value, int $limit): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        return mb_strlen($value) <= $limit ? $value : rtrim(mb_substr($value, 0, max(1, $limit - 3))) . '...';
    }
}

function render_admin_pdf(array $data): string
{
    $pdf = new AdminPdfDocument();
    $generated = (new DateTimeImmutable((string)$data['generated_at']))->format('j M Y, g:i a');
    $pdf->heading($generated);
    $pdf->summary($data['stats']);

    $pdf->section('User accounts by role');
    $userRows = array_map(static fn(array $row): array => [ucfirst((string)$row['role']), $row['active'], $row['pending'], $row['suspended'], $row['total']], $data['users_by_role']);
    $pdf->table(['Role','Active','Pending','Suspended','Total'], $userRows, [151,90,90,90,90]);

    $pdf->section('Businesses by review status');
    $businessRows = array_map(static fn(array $row): array => [ucfirst((string)$row['status']), $row['total']], $data['businesses_by_status']);
    $pdf->table(['Status','Businesses'], $businessRows, [340,171]);

    $pdf->section('Pending business registrations');
    $pendingRows = array_map(static fn(array $row): array => [$row['name'], $row['category'], $row['email']], $data['pending']);
    $pdf->table(['Business','Category','Owner email'], $pendingRows, [185,125,201]);

    $pdf->section('Recent administrative activity');
    $activityRows = array_map(static fn(array $row): array => [$row['created_at'], $row['administrator'], $row['action'], $row['area']], $data['recent_activity']);
    $pdf->table(['Date','Administrator','Action','Area'], $activityRows, [112,130,169,100]);
    return $pdf->output();
}
