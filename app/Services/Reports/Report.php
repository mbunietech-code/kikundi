<?php

namespace App\Services\Reports;

/**
 * Plain report data rendered the same way on screen, in PDF and in Excel.
 *
 * Each section: ['title' => string, 'columns' => [key => [label, type]],
 * 'rows' => list<array>, 'totals' => ?array]. Column type is "text",
 * "number" or "money".
 */
class Report
{
    /**
     * @param  array<string, string>  $summary  label => already formatted value
     * @param  list<array{title: string, columns: array<string, array{0: string, 1: string}>, rows: list<array<string, mixed>>, totals: ?array<string, mixed>}>  $sections
     */
    public function __construct(
        public readonly string $title,
        public readonly string $subtitle,
        public readonly array $summary,
        public readonly array $sections,
        public readonly string $filename,
    ) {}
}
