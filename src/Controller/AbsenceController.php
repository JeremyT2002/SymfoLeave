<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Absence;
use App\Entity\User;
use App\Form\AbsenceRequestFormType;
use App\Repository\AbsenceRepository;
use App\Security\Voter\AbsenceVoter;
use App\Service\AbsenceManager;
use App\Service\VacationAccountCalculator;
use App\Service\WorkingDayCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_EMPLOYEE')]
#[Route('/absences')]
final class AbsenceController extends AbstractController
{
    public function __construct(
        private readonly AbsenceManager $manager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'app_absence_index', methods: ['GET'])]
    public function index(#[CurrentUser] User $user, AbsenceRepository $absences, VacationAccountCalculator $accounts): Response
    {
        return $this->render('absence/index.html.twig', [
            'absences' => $absences->findForUser($user),
            'account' => $accounts->forUser($user),
        ]);
    }

    #[Route('/new', name: 'app_absence_new', methods: ['GET', 'POST'])]
    public function new(#[CurrentUser] User $user, Request $request, VacationAccountCalculator $accounts): Response
    {
        $absence = new Absence($user);
        $form = $this->createForm(AbsenceRequestFormType::class, $absence);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->manager->submit($absence);

            $this->addFlash('success', $this->translator->trans($absence->getType()?->requiresApproval() ? 'flash.requested' : 'flash.recorded'));

            if ($absence->getType()?->countsAgainstVacation()) {
                $year = (int) $absence->getStartDate()?->format('Y');
                foreach (array_unique([$year, (int) $absence->getEndDate()?->format('Y')]) as $y) {
                    $account = $accounts->forUser($user, $y);
                    if ($account->isNegative()) {
                        $this->addFlash('warning', $this->translator->trans('flash.negative_account', [
                            '%year%' => $y,
                            '%days%' => number_format($account->remaining(), 1, 'en' === $request->getLocale() ? '.' : ',', ''),
                        ]));
                    }
                }
            }

            return $this->redirectToRoute('app_absence_index');
        }

        return $this->render('absence/new.html.twig', [
            'form' => $form,
            'account' => $accounts->forUser($user),
        ], new Response(status: $form->isSubmitted() ? 422 : 200));
    }

    /** Live preview of the working days (Stimulus). */
    #[Route('/working-days', name: 'app_absence_working_days', methods: ['GET'])]
    public function workingDays(Request $request, WorkingDayCalculator $calculator): JsonResponse
    {
        try {
            $start = new \DateTimeImmutable((string) $request->query->get('start'));
            $end = new \DateTimeImmutable((string) $request->query->get('end'));
        } catch (\Exception) {
            return new JsonResponse(['days' => null]);
        }
        if ($end < $start || $start->diff($end)->days > 730) {
            return new JsonResponse(['days' => null]);
        }

        return new JsonResponse([
            'days' => $calculator->count($start, $end, $request->query->getBoolean('startHalf'), $request->query->getBoolean('endHalf')),
            'perYear' => $calculator->countPerYear($start, $end, $request->query->getBoolean('startHalf'), $request->query->getBoolean('endHalf')),
        ]);
    }

    #[Route('/{id}/cancel', name: 'app_absence_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(#[CurrentUser] User $user, Absence $absence, Request $request): Response
    {
        $this->denyAccessUnlessGranted(AbsenceVoter::CANCEL, $absence);
        if (!$this->isCsrfTokenValid('cancel'.$absence->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Ungültiges CSRF-Token.');
        }
        $this->manager->apply($absence, AbsenceManager::TRANSITION_CANCEL, $user);
        $this->addFlash('success', $this->translator->trans('flash.cancelled'));

        return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_absence_index'));
    }

    #[Route('/{id}/decide', name: 'app_absence_decide', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function decide(#[CurrentUser] User $user, Absence $absence, Request $request): Response
    {
        $this->denyAccessUnlessGranted(AbsenceVoter::DECIDE, $absence);
        if (!$this->isCsrfTokenValid('decide'.$absence->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Ungültiges CSRF-Token.');
        }
        $transition = (string) $request->request->get('decision');
        if (!in_array($transition, [AbsenceManager::TRANSITION_APPROVE, AbsenceManager::TRANSITION_REJECT], true)
            || !$this->manager->can($absence, $transition)) {
            throw $this->createAccessDeniedException('Diese Entscheidung ist nicht möglich.');
        }
        $comment = mb_substr(trim((string) $request->request->get('comment')), 0, 1000);
        $this->manager->apply($absence, $transition, $user, $comment);

        $this->addFlash('success', $this->translator->trans(
            $transition === AbsenceManager::TRANSITION_APPROVE ? 'flash.approved' : 'flash.rejected',
            ['%name%' => $absence->getUser()->getName()],
        ));

        return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_dashboard'));
    }
}
