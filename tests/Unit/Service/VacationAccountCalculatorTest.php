<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Absence;
use App\Entity\AbsenceStatus;
use App\Entity\AbsenceType;
use App\Entity\User;
use App\Repository\AbsenceRepository;
use App\Service\HolidayProvider;
use App\Service\VacationAccount;
use App\Service\VacationAccountCalculator;
use App\Service\WorkingDayCalculator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class VacationAccountCalculatorTest extends TestCase
{
    private VacationAccountCalculator $calculator;
    private User $user;
    private AbsenceType $vacation;
    private AbsenceType $sick;

    protected function setUp(): void
    {
        $this->calculator = new VacationAccountCalculator(
            new WorkingDayCalculator(new HolidayProvider('Germany/NorthRhineWestphalia')),
            $this->createStub(AbsenceRepository::class),
            new MockClock('2026-06-15'),
        );
        $this->user = (new User('Test Person'))->setEmail('test@example.org');
        $this->vacation = new AbsenceType('Urlaub', '#2563eb', true, true, true);
        $this->sick = new AbsenceType('Krankheit', '#dc2626', false, false, false);
    }

    private function absence(AbsenceType $type, string $start, string $end, string $status, bool $startHalf = false, bool $endHalf = false): Absence
    {
        return (new Absence($this->user))
            ->setType($type)
            ->setStartDate(new \DateTimeImmutable($start))
            ->setEndDate(new \DateTimeImmutable($end))
            ->setStartHalfDay($startHalf)
            ->setEndHalfDay($endHalf)
            ->setStatus($status);
    }

    public function testAccount(): void
    {
        $absences = [
            $this->absence($this->vacation, '2026-03-02', '2026-03-06', AbsenceStatus::APPROVED),   // 5 taken
            $this->absence($this->vacation, '2026-06-12', '2026-06-19', AbsenceStatus::APPROVED),   // 2 taken + 4 planned
            $this->absence($this->vacation, '2026-08-03', '2026-08-07', AbsenceStatus::REQUESTED),  // 5 planned
            $this->absence($this->vacation, '2026-12-28', '2027-01-08', AbsenceStatus::APPROVED),   // 4 planned in 2026
            $this->absence($this->sick, '2026-05-04', '2026-05-08', AbsenceStatus::APPROVED),       // does not count
            $this->absence($this->vacation, '2026-09-07', '2026-09-11', AbsenceStatus::CANCELLED),  // ignored
            $this->absence($this->vacation, '2026-10-05', '2026-10-09', AbsenceStatus::REJECTED),   // ignored
        ];

        $account = $this->calculator->calculate(30, $absences, 2026, new \DateTimeImmutable('2026-06-15'));

        self::assertSame(30.0, $account->entitlement);
        self::assertSame(7.0, $account->taken);
        self::assertSame(13.0, $account->planned);
        self::assertSame(10.0, $account->remaining());
        self::assertFalse($account->isNegative());
    }

    public function testNextYearGetsRemainderOfYearChange(): void
    {
        $absences = [$this->absence($this->vacation, '2026-12-28', '2027-01-08', AbsenceStatus::APPROVED)];

        $account = $this->calculator->calculate(30, $absences, 2027, new \DateTimeImmutable('2026-06-15'));

        self::assertSame(0.0, $account->taken);
        self::assertSame(5.0, $account->planned);
        self::assertSame(25.0, $account->remaining());
    }

    public function testHalfDaysAndNegativeBalance(): void
    {
        $absences = [
            $this->absence($this->vacation, '2026-02-02', '2026-02-27', AbsenceStatus::APPROVED, true, false), // 19.5 taken
            $this->absence($this->vacation, '2026-11-02', '2026-11-13', AbsenceStatus::REQUESTED),            // 10 planned
        ];

        $account = $this->calculator->calculate(25, $absences, 2026, new \DateTimeImmutable('2026-06-15'));

        self::assertSame(19.5, $account->taken);
        self::assertSame(10.0, $account->planned);
        self::assertSame(-4.5, $account->remaining());
        self::assertTrue($account->isNegative());
        self::assertSame(100, $account->usedPercent());
    }

    public function testValueObject(): void
    {
        $account = new VacationAccount(2026, 30, 10, 5);

        self::assertSame(15.0, $account->remaining());
        self::assertSame(50, $account->usedPercent());
    }
}
