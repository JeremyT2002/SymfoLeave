<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sprache jeder Anfrage: gewählte Sprache (Cookie) → Browsersprache → Standard (de).
 */
final class LocaleSubscriber implements EventSubscriberInterface
{
    public const COOKIE = 'locale';

    /** @param list<string> $locales */
    public function __construct(
        #[Autowire('%kernel.enabled_locales%')]
        private readonly array $locales,
        #[Autowire('%kernel.default_locale%')]
        private readonly string $defaultLocale,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // vor Symfonys LocaleListener (16), damit Translator und Formulare die Sprache kennen
        return [KernelEvents::REQUEST => ['onKernelRequest', 20]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $chosen = $request->cookies->get(self::COOKIE);

        if (\is_string($chosen) && \in_array($chosen, $this->locales, true)) {
            $request->setLocale($chosen);

            return;
        }

        $request->setLocale($request->getPreferredLanguage($this->locales) ?? $this->defaultLocale);
    }
}
