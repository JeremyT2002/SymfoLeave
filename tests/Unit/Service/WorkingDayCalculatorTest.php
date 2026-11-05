<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\HolidayProvider;
use App\Service\WorkingDayCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WorkingDayCalculatorTest extends TestCase
{
    private WorkingDayCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new WorkingDayCalculator(new HolidayProvider('Germany/NorthRhineWestphalia'));
    }

    private static function d(string $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date);
    }

    /** @return iterable<string, array{string, string, bool, bool, float}> */
    public static function cases(): iterable
    {
        yield 'Mo–Fr' => ['2026-11-09', '2026-11-13', false, false, 5.0];
        yield 'Wochenende zählt nicht' => ['2026-11-09', '2026-11-15', false, false, 5.0];
        yield 'nur Wochenende' => ['2026-11-14', '2026-11-15', false, false, 0.0];
        yield 'Weihnachten (Feiertag)' => ['2026-12-21', '2026-12-25', false, false, 4.0];
        yield 'Fronleichnam NRW' => ['2026-06-01', '2026-06-05', false, false, 4.0];
        yield 'halbe Tage am Anfang und Ende' => ['2026-11-09', '2026-11-13', true, true, 4.0];
        yield 'einzelner halber Tag' => ['2026-11-10', '2026-11-10', true, false, 0.5];
        yield 'halber Tag auf Wochenende' => ['2026-11-13', '2026-11-14', false, true, 1.0];
        yield 'Ende vor Start' => ['2026-11-13', '2026-11-09', false, false, 0.0];
    }

    #[DataProvider('cases')]
    public function testCount(string $start, string $end, bool $startHalf, bool $endHalf, float $expected): void
    {
        self::assertSame($expected, $this->calculator->count(self::d($start), self::d($end), $startHalf, $endHalf));
    }

    public function testFronleichnamIsNoHolidayInBerlin(): void
    {
        $berlin = new WorkingDayCalculator(new HolidayProvider('Germany/Berlin'));

        self::assertSame(5.0, $berlin->count(self::d('2026-06-01'), self::d('2026-06-05')));
    }

    public function testYearChangeIsSplitPerYear(): void
    {
        // 28.–31.12.2026 = 4 days; 01.01.2027 holiday; 04.–08.01.2027 = 5 days
        $perYear = $this->calculator->countPerYear(self::d('2026-12-28'), self::d('2027-01-08'));

        self::assertSame([2026 => 4.0, 2027 => 5.0], $perYear);
        self::assertSame(4.0, $this->calculator->countInYear(2026, self::d('2026-12-28'), self::d('2027-01-08')));
        self::assertSame(5.0, $this->calculator->countInYear(2027, self::d('2026-12-28'), self::d('2027-01-08')));
        self::assertSame(0.0, $this->calculator->countInYear(2025, self::d('2026-12-28'), self::d('2027-01-08')));
    }

    public function testHalfDaysAcrossYearChange(): void
    {
        $perYear = $this->calculator->countPerYear(self::d('2026-12-30'), self::d('2027-01-04'), true, true);

        self::assertSame([2026 => 1.5, 2027 => 0.5], $perYear);
    }
}
