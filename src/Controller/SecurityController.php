<?php

declare(strict_types=1);

namespace App\Controller;

use App\Demo\DemoDataLoader;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Translation\TranslatorInterface;

final class SecurityController extends AbstractController
{
    private const DEMO_ACCOUNTS = [
        'employee' => DemoDataLoader::EMPLOYEE_EMAIL,
        'teamlead' => DemoDataLoader::TEAM_LEAD_EMAIL,
        'admin' => DemoDataLoader::ADMIN_EMAIL,
    ];

    public function __construct(
        #[Autowire('%app.demo%')]
        private readonly bool $demo,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $auth): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $auth->getLastUsername(),
            'error' => $auth->getLastAuthenticationError(),
        ]);
    }

    #[Route('/demo-login/{role}', name: 'app_demo_login', requirements: ['role' => 'employee|teamlead|admin'], methods: ['POST'])]
    public function demoLogin(string $role, Request $request, UserRepository $users, Security $security): Response
    {
        if (!$this->demo) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('demo-login', (string) $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('flash.invalid_token'));

            return $this->redirectToRoute('app_login');
        }
        $user = $users->findOneBy(['email' => self::DEMO_ACCOUNTS[$role]]);
        if ($user === null) {
            $this->addFlash('error', $this->translator->trans('flash.demo_missing'));

            return $this->redirectToRoute('app_login');
        }
        $security->login($user, 'form_login', 'main');

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('Wird von der Firewall abgefangen.');
    }
}
