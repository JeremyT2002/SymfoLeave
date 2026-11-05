<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\UserFormType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/users')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    #[Route('', name: 'app_admin_user_index', methods: ['GET'])]
    public function index(UserRepository $users): Response
    {
        return $this->render('admin/user/index.html.twig', ['users' => $users->findBy([], ['name' => 'ASC'])]);
    }

    #[Route('/new', name: 'app_admin_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        return $this->handleForm($request, new User(), true);
    }

    #[Route('/{id}/edit', name: 'app_admin_user_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user): Response
    {
        return $this->handleForm($request, $user, false);
    }

    #[Route('/{id}/delete', name: 'app_admin_user_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, User $user, #[CurrentUser] User $me): Response
    {
        if (!$this->isCsrfTokenValid('delete-user'.$user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Ungültiges CSRF-Token.');
        }
        if ($user === $me) {
            $this->addFlash('error', 'Du kannst dich nicht selbst löschen.');
        } else {
            $this->em->remove($user);
            $this->em->flush();
            $this->addFlash('success', 'Person gelöscht.');
        }

        return $this->redirectToRoute('app_admin_user_index');
    }

    private function handleForm(Request $request, User $user, bool $isNew): Response
    {
        $form = $this->createForm(UserFormType::class, $user, ['require_password' => $isNew]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = (string) $form->get('plainPassword')->getData();
            if ($plain !== '') {
                $user->setPassword($this->hasher->hashPassword($user, $plain));
            }
            $this->em->persist($user);
            $this->em->flush();
            $this->addFlash('success', $isNew ? 'Person angelegt.' : 'Änderungen gespeichert.');

            return $this->redirectToRoute('app_admin_user_index');
        }

        return $this->render('admin/user/form.html.twig', [
            'form' => $form,
            'user' => $user,
            'isNew' => $isNew,
        ], new Response(status: $form->isSubmitted() ? 422 : 200));
    }
}
