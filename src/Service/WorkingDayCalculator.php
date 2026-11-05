<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Counts working days (Mon–Fri without statutory holidays).
 * A half day at the start/end counts 0.5.
 */
class WorkingDayCalculator
{
    public function __construct(private readonly HolidayProvider $holidays)
    {
    }

    public function isWorkingDay(\DateTimeImmutable $day): bool
    {
        return (int) $day->format('N') <= 5 && !$this->holidays->isHoliday($day);
    }

    public function count(\DateTimeImmutable $start, \DateTimeImmutable $end, bool $startHalfDay = false, bool $endHalfDay = false): float
    {
        return array_sum($this->countPerYear($start, $end, $startHalfDay, $endHalfDay));
    }

    /**
     * Working days split by calendar year (for absences spanning New Year).
     *
     * @return array<int, float> year => days
     */
    public function countPerYear(\DateTimeImmutable $start, \DateTimeImmutable $end, bool $startHalfDay = false, bool $endHalfDay = false): array
    {
        $start = $start->setTime(0, 0);
        $end = $end->setTime(0, 0);
        if ($end < $start) {
            return [];
        }

        $result = [];
        for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
            $year = (int) $day->format('Y');
            $result[$year] ??= 0.0;
            if (!$this->isWorkingDay($day)) {
                continue;
            }
            $isHalf = ($startHalfDay && $day == $start) || ($endHalfDay && $day == $end);
            $result[$year] += $isHalf ? 0.5 : 1.0;
        }

        return $result;
    }

    /** Working days of [start, end] that fall within the given year. */
    public function countInYear(int $year, \DateTimeImmutable $start, \DateTimeImmutable $end, bool $startHalfDay = false, bool $endHalfDay = false): float
    {
        return $this->countPerYear($start, $end, $startHalfDay, $endHalfDay)[$year] ?? 0.0;
    }
}
