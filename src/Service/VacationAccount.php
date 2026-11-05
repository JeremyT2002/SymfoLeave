<?php

declare(strict_types=1);

namespace App\Service;

final readonly class VacationAccount
{
    public function __construct(
        public int $year,
        public float $entitlement,
        /** Approved vacation days up to and including today. */
        public float $taken,
        /** Approved future days + pending requests. */
        public float $planned,
    ) {
    }

    public function remaining(): float
    {
        return $this->entitlement - $this->taken - $this->planned;
    }

    public function isNegative(): bool
    {
        return $this->remaining() < 0;
    }

    public function usedPercent(): int
    {
        if ($this->entitlement <= 0) {
            return 100;
        }

        return (int) min(100, round(($this->taken + $this->planned) / $this->entitlement * 100));
    }
}
