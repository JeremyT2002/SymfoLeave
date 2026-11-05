<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Absence;
use App\Entity\AbsenceStatus;
use App\Entity\User;
use App\Repository\AbsenceRepository;
use Psr\Clock\ClockInterface;

/**
 * Vacation account per year: entitlement, taken, planned (approved future + pending), remaining.
 */
class VacationAccountCalculator
{
    public function __construct(
        private readonly WorkingDayCalculator $workingDays,
        private readonly AbsenceRepository $absences,
        private readonly ClockInterface $clock,
    ) {
    }

    public function forUser(User $user, ?int $year = null, ?Absence $additional = null): VacationAccount
    {
        $today = $this->clock->now()->setTime(0, 0);
        $year ??= (int) $today->format('Y');
        $list = $this->absences->findActiveForUserInYear($user, $year);
        if ($additional !== null && !in_array($additional, $list, true)) {
            $list[] = $additional;
        }

        return $this->calculate($user->getAnnualVacationDays(), $list, $year, $today);
    }

    /** @param iterable<Absence> $absences */
    public function calculate(int $entitlement, iterable $absences, int $year, \DateTimeImmutable $today): VacationAccount
    {
        $today = $today->setTime(0, 0);
        $taken = 0.0;
        $planned = 0.0;

        foreach ($absences as $a) {
            $type = $a->getType();
            if ($type === null || !$type->countsAgainstVacation() || !$a->isActive()) {
                continue;
            }
            $start = $a->getStartDate();
            $end = $a->getEndDate();
            if ($start === null || $end === null) {
                continue;
            }
            $inYear = $this->workingDays->countInYear($year, $start, $end, $a->isStartHalfDay(), $a->isEndHalfDay());

            if ($a->getStatus() === AbsenceStatus::REQUESTED) {
                $planned += $inYear;
                continue;
            }

            // Approved: split into past (incl. today) and future part.
            if ($end <= $today) {
                $taken += $inYear;
            } elseif ($start > $today) {
                $planned += $inYear;
            } else {
                $past = $this->workingDays->countInYear($year, $start, $today, $a->isStartHalfDay(), false);
                $taken += $past;
                $planned += $inYear - $past;
            }
        }

        return new VacationAccount($year, (float) $entitlement, $taken, $planned);
    }
}
