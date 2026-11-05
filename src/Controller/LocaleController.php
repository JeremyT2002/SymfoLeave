<?php

declare(strict_types=1);

namespace App\Controller;

use App\EventSubscriber\LocaleSubscriber;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class LocaleController extends AbstractController
{
    /** @param list<string> $locales */
    public function __construct(
        #[Autowire('%kernel.enabled_locales%')]
        private readonly array $locales,
    ) {
    }

    /** Merkt sich die Sprache für ein Jahr und kehrt zur vorherigen Seite zurück. */
    #[Route('/locale/{locale}', name: 'app_locale', requirements: ['locale' => '[a-z]{2}'], methods: ['GET'])]
    public function switch(string $locale, Request $request): RedirectResponse
    {
        if (!\in_array($locale, $this->locales, true)) {
            throw $this->createNotFoundException();
        }

        // Nur interne Pfade als Rücksprungziel – kein offener Redirect
        $target = (string) $request->query->get('redirect', '/');
        if (!str_starts_with($target, '/') || str_starts_with($target, '//')) {
            $target = '/';
        }

        $response = new RedirectResponse($target);
        $response->headers->setCookie(
            Cookie::create(LocaleSubscriber::COOKIE, $locale, new \DateTimeImmutable('+1 year'))
                ->withSecure($request->isSecure())
                ->withSameSite(Cookie::SAMESITE_LAX),
        );

        return $response;
    }
}
