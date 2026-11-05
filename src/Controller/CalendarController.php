<?php

declare(strict_types=1);

namespace App\Controller;

use App\Calendar\MonthCalendarBuilder;
use App\Entity\User;
use App\Repository\AbsenceTypeRepository;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EMPLOYEE')]
final class CalendarController extends AbstractController
{
    #[Route('/calendar', name: 'app_calendar', methods: ['GET'])]
    public function index(
        #[CurrentUser] User $user,
        Request $request,
        MonthCalendarBuilder $builder,
        TeamRepository $teams,
        UserRepository $users,
        ClockInterface $clock,
        AbsenceTypeRepository $types,
    ): Response {
        $monthParam = (string) $request->query->get('month', '');
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthParam)
            ? new \DateTimeImmutable($monthParam.'-01')
            : $clock->now()->modify('first day of this month');

        $allTeams = $teams->findBy([], ['name' => 'ASC']);
        $teamParam = $request->query->get('team');
        if ($teamParam === 'all') {
            $team = null;
        } elseif ($teamParam !== null && ($found = $teams->find((int) $teamParam))) {
            $team = $found;
        } else {
            $team = $user->getTeam();
        }

        $members = $team !== null
            ? $users->findBy(['team' => $team], ['name' => 'ASC'])
            : $users->findBy([], ['name' => 'ASC']);

        return $this->render('calendar/index.html.twig', [
            'calendar' => $builder->build($month, $members),
            'teams' => $allTeams,
            'team' => $team,
            'legend' => $types->findBy([], ['id' => 'ASC']),
        ]);
    }
}
