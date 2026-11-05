<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class LocaleTest extends AppWebTestCase
{
    public function testBrowserLanguageIsUsed(): void
    {
        $this->client->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'fr-FR,fr;q=0.9');
        $this->client->request('GET', '/login');

        self::assertSelectorExists('html[lang=fr]');
        self::assertSelectorTextContains('body', 'Démo – toutes les données sont fictives');
    }

    public function testUnknownBrowserLanguageFallsBackToGerman(): void
    {
        $this->client->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'es-ES');
        $this->client->request('GET', '/login');

        self::assertSelectorExists('html[lang=de]');
    }

    public function testSwitchingLanguageSetsCookieAndRedirectsSafely(): void
    {
        $this->client->request('GET', '/locale/en?redirect=/login');
        self::assertResponseRedirects('/login');
        self::assertResponseHasCookie('locale');

        $this->client->followRedirect();
        self::assertSelectorExists('html[lang=en]');
        self::assertSelectorTextContains('body', 'Demo – all data is fictitious');

        // keine offenen Weiterleitungen auf fremde Hosts
        $this->client->request('GET', '/locale/fr?redirect=//evil.example');
        self::assertResponseRedirects('/');
    }

    public function testUnsupportedLocaleIs404(): void
    {
        $this->client->request('GET', '/locale/xx');
        self::assertResponseStatusCodeSame(404);
    }
}
