<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;

final class DemoLoginTest extends AppWebTestCase
{
    public function testAnonymousIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/');
        self::assertResponseRedirects('/login');
    }

    /** @return iterable<array{string, string, string}> */
    public static function roles(): iterable
    {
        yield ['Als Mitarbeiter:in testen', 'Max Müller', 'Mitarbeiter:in'];
        yield ['Als Teamleitung testen', 'Lena Hoffmann', 'Teamleitung'];
        yield ['Als Admin testen', 'Sabine Wagner', 'Admin'];
    }

    #[DataProvider('roles')]
    public function testDemoLogin(string $button, string $name, string $roleLabel): void
    {
        $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Demo – alle Daten sind fiktiv');

        $this->client->submitForm($button);
        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('header', $name);
        self::assertSelectorTextContains('header', $roleLabel);
    }

    public function testDemoLoginRequiresCsrf(): void
    {
        $this->client->request('POST', '/demo-login/admin', ['_token' => 'invalid']);
        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role=status]', 'Ungültiges Formular-Token');
    }

    public function testAdminAreaIsProtected(): void
    {
        $this->loginAs('max.mueller@nordlicht.example');
        $this->client->request('GET', '/admin/users');
        self::assertResponseStatusCodeSame(403);

        $this->loginAs('sabine.wagner@nordlicht.example');
        $this->client->request('GET', '/admin/users');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/admin/teams');
        self::assertResponseIsSuccessful();
    }

    public function testWorkingDaysEndpoint(): void
    {
        $this->loginAs('max.mueller@nordlicht.example');
        $this->client->request('GET', '/absences/working-days?start=2026-12-21&end=2026-12-25');
        self::assertResponseIsSuccessful();
        self::assertEquals(4.0, json_decode((string) $this->client->getResponse()->getContent(), true)['days']);
    }
}
