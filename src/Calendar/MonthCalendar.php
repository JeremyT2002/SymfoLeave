<?php

declare(strict_types=1);

namespace App\Calendar;

final readonly class MonthCalendar
{
    /**
     * @param list<array{date: \DateTimeImmutable, weekend: bool, holiday: ?string, today: bool}> $days
     * @param list<array{user: \App\Entity\User, cells: array<string, array{label: string, color: string, half: bool, pending: bool, comment: ?string}>}> $rows
     */
    public function __construct(
        public \DateTimeImmutable $month,
        public array $days,
        public array $rows,
    ) {
    }

    public function previous(): \DateTimeImmutable
    {
        return $this->month->modify('first day of previous month');
    }

    public function next(): \DateTimeImmutable
    {
        return $this->month->modify('first day of next month');
    }
}
