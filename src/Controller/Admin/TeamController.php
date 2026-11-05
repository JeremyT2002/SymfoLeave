<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Team;
use App\Form\TeamFormType;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/teams')]
final class TeamController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('', name: 'app_admin_team_index', methods: ['GET'])]
    public function index(TeamRepository $teams): Response
    {
        return $this->render('admin/team/index.html.twig', ['teams' => $teams->findBy([], ['name' => 'ASC'])]);
    }

    #[Route('/new', name: 'app_admin_team_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        return $this->handleForm($request, new Team(), true);
    }

    #[Route('/{id}/edit', name: 'app_admin_team_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Team $team): Response
    {
        return $this->handleForm($request, $team, false);
    }

    #[Route('/{id}/delete', name: 'app_admin_team_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Team $team): Response
    {
        if (!$this->isCsrfTokenValid('delete-team'.$team->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Ungültiges CSRF-Token.');
        }
        $this->em->remove($team);
        $this->em->flush();
        $this->addFlash('success', 'Team gelöscht. Mitglieder sind jetzt keinem Team zugeordnet.');

        return $this->redirectToRoute('app_admin_team_index');
    }

    private function handleForm(Request $request, Team $team, bool $isNew): Response
    {
        $form = $this->createForm(TeamFormType::class, $team);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($team);
            $this->em->flush();
            $this->addFlash('success', $isNew ? 'Team angelegt.' : 'Änderungen gespeichert.');

            return $this->redirectToRoute('app_admin_team_index');
        }

        return $this->render('admin/team/form.html.twig', [
            'form' => $form,
            'team' => $team,
            'isNew' => $isNew,
        ], new Response(status: $form->isSubmitted() ? 422 : 200));
    }
}
