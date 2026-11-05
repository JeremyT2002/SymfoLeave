<?php

declare(strict_types=1);

namespace App\Demo;

use App\Entity\Absence;
use App\Entity\AbsenceStatus;
use App\Entity\AbsenceType;
use App\Entity\Team;
use App\Entity\User;
use App\Service\WorkingDayCalculator;
use Doctrine\Persistence\ObjectManager;
use Psr\Clock\ClockInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Creates a fictitious company with absences relative to "today".
 * Used by the fixtures (dev/test) and by app:demo:reset (also in prod).
 */
class DemoDataLoader
{
    public const DEMO_PASSWORD = 'demo';
    public const EMPLOYEE_EMAIL = 'max.mueller@nordlicht.example';
    public const TEAM_LEAD_EMAIL = 'lena.hoffmann@nordlicht.example';
    public const ADMIN_EMAIL = 'sabine.wagner@nordlicht.example';
    public const OTHER_TEAM_LEAD_EMAIL = 'tobias.krueger@nordlicht.example';

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly WorkingDayCalculator $workingDays,
        private readonly ClockInterface $clock,
    ) {
    }

    public function load(ObjectManager $em, ?\DateTimeImmutable $today = null): void
    {
        $today = ($today ?? $this->clock->now())->setTime(0, 0);

        // Absence types
        $types = [
            'vacation' => new AbsenceType('Urlaub', '#2563eb', requiresApproval: true, countsAgainstVacation: true, visibleToColleagues: true),
            'sick' => new AbsenceType('Krankheit', '#dc2626', requiresApproval: false, countsAgainstVacation: false, visibleToColleagues: false),
            'home' => new AbsenceType('Homeoffice', '#0d9488', requiresApproval: true, countsAgainstVacation: false, visibleToColleagues: true),
            'training' => new AbsenceType('Fortbildung', '#9333ea', requiresApproval: true, countsAgainstVacation: false, visibleToColleagues: true),
            'special' => new AbsenceType('Sonderurlaub', '#d97706', requiresApproval: true, countsAgainstVacation: false, visibleToColleagues: true),
        ];
        foreach ($types as $type) {
            $em->persist($type);
        }

        // Teams & people
        $dev = new Team('Entwicklung');
        $sales = new Team('Vertrieb & Service');
        $em->persist($dev);
        $em->persist($sales);

        $hash = null;
        $people = [];
        $make = function (string $key, string $name, string $email, Team $team, array $roles = [], int $days = 30) use (&$people, &$hash, $em): User {
            $u = (new User($name))->setEmail($email)->setTeam($team)->setRoles($roles)->setAnnualVacationDays($days);
            $hash ??= $this->hasher->hashPassword($u, self::DEMO_PASSWORD);
            $u->setPassword($hash);
            $em->persist($u);
            $team->getMembers()->add($u);

            return $people[$key] = $u;
        };

        $make('lena', 'Lena Hoffmann', self::TEAM_LEAD_EMAIL, $dev, [User::ROLE_TEAM_LEAD]);
        $make('max', 'Max Müller', self::EMPLOYEE_EMAIL, $dev);
        $make('jonas', 'Jonas Becker', 'jonas.becker@nordlicht.example', $dev, [], 28);
        $make('aylin', 'Aylin Demir', 'aylin.demir@nordlicht.example', $dev);
        $make('paul', 'Paul Schneider', 'paul.schneider@nordlicht.example', $dev, [], 27);
        $make('elias', 'Elias Neumann', 'elias.neumann@nordlicht.example', $dev);
        $make('tobias', 'Tobias Krüger', self::OTHER_TEAM_LEAD_EMAIL, $sales, [User::ROLE_TEAM_LEAD]);
        $make('sophie', 'Sophie Wolf', 'sophie.wolf@nordlicht.example', $sales);
        $make('mia', 'Mia Schulz', 'mia.schulz@nordlicht.example', $sales, [], 25);
        $make('felix', 'Felix Braun', 'felix.braun@nordlicht.example', $sales);
        $make('sabine', 'Sabine Wagner', self::ADMIN_EMAIL, $sales, [User::ROLE_ADMIN]);

        $dev->setLead($people['lena']);
        $sales->setLead($people['tobias']);

        // [person, type, start offset, end offset, status, startHalf, endHalf, comment]
        $plan = [
            ['max', 'vacation', -28, -24, AbsenceStatus::APPROVED, false, false, 'Kurzurlaub an der Ostsee'],
            ['max', 'home', 2, 2, AbsenceStatus::APPROVED, false, false, null],
            ['max', 'vacation', 21, 25, AbsenceStatus::REQUESTED, false, false, 'Familienbesuch'],
            ['max', 'vacation', 56, 64, AbsenceStatus::APPROVED, false, true, 'Sommerurlaub'],
            ['lena', 'vacation', -14, -10, AbsenceStatus::APPROVED, false, false, null],
            ['lena', 'training', 14, 15, AbsenceStatus::APPROVED, false, false, 'Symfony-Konferenz'],
            ['jonas', 'sick', -1, 1, AbsenceStatus::APPROVED, false, false, 'Grippe'],
            ['jonas', 'vacation', 7, 11, AbsenceStatus::REQUESTED, false, false, 'Umzug'],
            ['aylin', 'vacation', -2, 5, AbsenceStatus::APPROVED, false, false, 'Städtereise'],
            ['aylin', 'home', 9, 9, AbsenceStatus::REQUESTED, false, false, 'Handwerker kommt'],
            ['paul', 'home', 1, 1, AbsenceStatus::REQUESTED, false, false, null],
            ['paul', 'special', 30, 30, AbsenceStatus::APPROVED, false, false, 'Hochzeit'],
            ['paul', 'vacation', -45, -41, AbsenceStatus::APPROVED, false, false, null],
            ['elias', 'sick', -20, -18, AbsenceStatus::APPROVED, false, false, null],
            ['elias', 'vacation', 3, 3, AbsenceStatus::REQUESTED, true, false, 'Arzttermin am Nachmittag'],
            ['tobias', 'vacation', 35, 44, AbsenceStatus::APPROVED, false, false, null],
            ['sophie', 'vacation', -40, -36, AbsenceStatus::APPROVED, false, false, null],
            ['sophie', 'vacation', 10, 19, AbsenceStatus::REQUESTED, false, false, 'Rundreise'],
            ['mia', 'training', 0, 1, AbsenceStatus::APPROVED, false, false, 'Verkaufstraining'],
            ['mia', 'vacation', 4, 4, AbsenceStatus::REQUESTED, true, false, null],
            ['felix', 'home', 0, 0, AbsenceStatus::APPROVED, false, false, null],
            ['felix', 'vacation', 5, 6, AbsenceStatus::REJECTED, false, false, 'Brückentage'],
            ['felix', 'sick', 12, 13, AbsenceStatus::APPROVED, false, false, null],
            ['sabine', 'vacation', -7, -3, AbsenceStatus::APPROVED, false, false, null],
            ['sabine', 'vacation', 28, 32, AbsenceStatus::REQUESTED, false, false, null],
        ];

        foreach ($plan as [$who, $typeKey, $from, $to, $status, $startHalf, $endHalf, $comment]) {
            $user = $people[$who];
            // Snap to working days so the demo never shows 0-day requests on weekends.
            $start = $this->nextWorkingDay($today->modify(sprintf('%+d days', $from)));
            $end = $this->previousWorkingDay($today->modify(sprintf('%+d days', $to)));
            if ($end < $start) {
                $end = $start;
            }
            $absence = (new Absence($user))
                ->setType($types[$typeKey])
                ->setStartDate($start)
                ->setEndDate($end)
                ->setStartHalfDay($startHalf)
                ->setEndHalfDay($endHalf)
                ->setComment($comment)
                ->setStatus($status)
                ->setWorkingDays($this->workingDays->count($start, $end, $startHalf, $endHalf))
                ->setCreatedAt(min($today, $start)->modify('-10 days')->setTime(9, 15));

            if ($status === AbsenceStatus::APPROVED || $status === AbsenceStatus::REJECTED) {
                $lead = $user->getTeam()?->getLead();
                $decider = $types[$typeKey]->requiresApproval()
                    ? ($lead === $user ? $people['sabine'] : $lead)
                    : null;
                $absence->recordDecision(
                    $decider,
                    $status === AbsenceStatus::REJECTED ? 'Leider zu viele Kolleg:innen gleichzeitig abwesend.' : null,
                );
            }
            $em->persist($absence);
        }

        $em->flush();
    }

    private function nextWorkingDay(\DateTimeImmutable $day): \DateTimeImmutable
    {
        while (!$this->workingDays->isWorkingDay($day)) {
            $day = $day->modify('+1 day');
        }

        return $day;
    }

    private function previousWorkingDay(\DateTimeImmutable $day): \DateTimeImmutable
    {
        while (!$this->workingDays->isWorkingDay($day)) {
            $day = $day->modify('-1 day');
        }

        return $day;
    }
}
