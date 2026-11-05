<?php

declare(strict_types=1);

namespace App\Calendar;

use App\Entity\AbsenceStatus;
use App\Entity\User;
use App\Repository\AbsenceRepository;
use App\Service\HolidayProvider;
use Psr\Clock\ClockInterface;

class MonthCalendarBuilder
{
    public function __construct(
        private readonly AbsenceRepository $absences,
        private readonly HolidayProvider $holidays,
        private readonly AbsenceVisibility $visibility,
        private readonly ClockInterface $clock,
    ) {
    }

    /** @param list<User> $users */
    public function build(\DateTimeImmutable $month, array $users): MonthCalendar
    {
        $first = $month->modify('first day of this month')->setTime(0, 0);
        $last = $first->modify('last day of this month');
        $today = $this->clock->now()->format('Y-m-d');

        $days = [];
        for ($d = $first; $d <= $last; $d = $d->modify('+1 day')) {
            $days[] = [
                'date' => $d,
                'weekend' => (int) $d->format('N') >= 6,
                'holiday' => $this->holidays->nameOf($d),
                'today' => $d->format('Y-m-d') === $today,
            ];
        }

        $rows = [];
        foreach ($users as $user) {
            $rows[$user->getId()] = ['user' => $user, 'cells' => []];
        }

        foreach ($this->absences->findActiveInRange($first, $last, $users) as $absence) {
            $id = $absence->getUser()->getId();
            if (!isset($rows[$id])) {
                continue;
            }
            $cell = [
                'label' => $this->visibility->label($absence),
                'color' => $this->visibility->color($absence),
                'pending' => $absence->getStatus() === AbsenceStatus::REQUESTED,
                'comment' => $this->visibility->comment($absence),
            ];
            $from = max($first, $absence->getStartDate());
            $to = min($last, $absence->getEndDate());
            for ($d = $from; $d <= $to; $d = $d->modify('+1 day')) {
                $rows[$id]['cells'][$d->format('Y-m-d')] = $cell + ['half' => $absence->isHalfOn($d)];
            }
        }

        return new MonthCalendar($first, $days, array_values($rows));
    }
}
