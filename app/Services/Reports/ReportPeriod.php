<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * A month, a quarter or a year, read from the report filters.
 */
class ReportPeriod
{
    public const TYPES = ['month' => 'Mwezi', 'quarter' => 'Robo mwaka', 'year' => 'Mwaka'];

    public function __construct(
        public readonly string $type,
        public readonly int $year,
        public readonly int $number,
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $now = CarbonImmutable::now();
        $type = array_key_exists($request->query('type'), self::TYPES) ? $request->query('type') : 'month';
        $year = (int) $request->query('year', $now->year);
        $year = $year >= 2000 && $year <= $now->year + 1 ? $year : $now->year;

        return match ($type) {
            'year' => self::year($year),
            'quarter' => self::quarter($year, (int) $request->query('quarter', $now->quarter)),
            default => self::month($year, (int) $request->query('month', $now->month)),
        };
    }

    public static function month(int $year, int $month): self
    {
        $month = min(12, max(1, $month));
        $start = CarbonImmutable::create($year, $month, 1)->startOfDay();

        return new self('month', $year, $month, $start, $start->endOfMonth());
    }

    public static function quarter(int $year, int $quarter): self
    {
        $quarter = min(4, max(1, $quarter));
        $start = CarbonImmutable::create($year, ($quarter - 1) * 3 + 1, 1)->startOfDay();

        return new self('quarter', $year, $quarter, $start, $start->addMonths(2)->endOfMonth());
    }

    public static function year(int $year): self
    {
        $start = CarbonImmutable::create($year, 1, 1)->startOfDay();

        return new self('year', $year, 1, $start, $start->endOfYear());
    }

    public function label(): string
    {
        return match ($this->type) {
            'year' => __('Mwaka :year', ['year' => $this->year]),
            'quarter' => __('Robo ya :number, :year', ['number' => $this->number, 'year' => $this->year]).' ('.$this->monthName($this->start).' – '.$this->monthName($this->end).')',
            default => $this->monthName($this->start).' '.$this->year,
        };
    }

    /** First and last dues period (Y-m) covered. */
    public function periods(): array
    {
        return [$this->start->format('Y-m'), $this->end->format('Y-m')];
    }

    /** @return list<CarbonImmutable> first day of each month covered */
    public function months(): array
    {
        $months = [];

        for ($m = $this->start; $m <= $this->end; $m = $m->addMonth()) {
            $months[] = $m;
        }

        return $months;
    }

    public function monthName(CarbonImmutable $date): string
    {
        return $date->locale(app()->getLocale())->translatedFormat('F');
    }

    /** Query string that reproduces this period, for links and downloads. */
    public function query(): array
    {
        return array_filter([
            'type' => $this->type,
            'year' => $this->year,
            'month' => $this->type === 'month' ? $this->number : null,
            'quarter' => $this->type === 'quarter' ? $this->number : null,
        ]);
    }

    public function slug(): string
    {
        return match ($this->type) {
            'year' => (string) $this->year,
            'quarter' => "{$this->year}-Q{$this->number}",
            default => $this->start->format('Y-m'),
        };
    }
}
