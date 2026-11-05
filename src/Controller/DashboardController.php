<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\AbsenceRepository;
use App\Service\VacationAccountCalculator;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EMPLOYEE')]
final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function index(
        #[CurrentUser] User $user,
        AbsenceRepository $absences,
        VacationAccountCalculator $accounts,
        ClockInterface $clock,
    ): Response {
        $today = $clock->now()->setTime(0, 0);

        $pending = [];
        if ($this->isGranted('ROLE_ADMIN')) {
            $pending = $absences->findPending(null);
        } elseif ($this->isGranted('ROLE_TEAM_LEAD') && $user->getTeam() && $user->isLeadOf($user->getTeam())) {
            $pending = array_values(array_filter(
                $absences->findPending($user->getTeam()),
                static fn ($a) => $a->getUser() !== $user,
            ));
        }

        return $this->render('dashboard/index.html.twig', [
            'account' => $accounts->forUser($user),
            'upcoming' => $absences->findUpcomingForUser($user, $today),
            'pending' => $pending,
            'absentToday' => $absences->findActiveInRange($today, $today),
            'today' => $today,
        ]);
    }
}
