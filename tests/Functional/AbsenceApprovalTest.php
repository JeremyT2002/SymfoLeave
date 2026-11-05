<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Demo\DemoDataLoader;
use App\Entity\Absence;
use App\Entity\AbsenceStatus;

final class AbsenceApprovalTest extends AppWebTestCase
{
    private function nextMonday(int $weeksAhead): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('monday next week'))->modify(sprintf('+%d weeks', $weeksAhead));
    }

    public function testRequestAndApprove(): void
    {
        // Employee requests vacation
        $employee = $this->loginAs(DemoDataLoader::EMPLOYEE_EMAIL);
        $crawler = $this->client->request('GET', '/absences/new');
        self::assertResponseIsSuccessful();

        $start = $this->nextMonday(20);
        $vacationTypeId = $crawler->filter('select[name="absence_request_form[type]"] option')
            ->reduce(static fn ($o) => $o->text() === 'Urlaub')->attr('value');

        $this->client->submitForm('Beantragen', [
            'absence_request_form[type]' => $vacationTypeId,
            'absence_request_form[startDate]' => $start->format('Y-m-d'),
            'absence_request_form[endDate]' => $start->modify('+4 days')->format('Y-m-d'),
            'absence_request_form[comment]' => 'Functional-Test',
        ]);
        self::assertResponseRedirects('/absences');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role=status]', 'Antrag gestellt');

        /** @var Absence $absence */
        $absence = $this->em->getRepository(Absence::class)->findOneBy(['comment' => 'Functional-Test']);
        self::assertSame(AbsenceStatus::REQUESTED, $absence->getStatus());
        self::assertSame($employee->getId(), $absence->getUser()->getId());
        self::assertGreaterThan(0, $absence->getWorkingDays());

        // Team lead of the same team approves it on the dashboard
        $this->loginAs(DemoDataLoader::TEAM_LEAD_EMAIL);
        $crawler = $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter(sprintf('#pending-%d form', $absence->getId()))->selectButton('Genehmigen')->form([
            'comment' => 'Viel Spaß!',
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects();

        $this->em->clear();
        $absence = $this->em->getRepository(Absence::class)->find($absence->getId());
        self::assertSame(AbsenceStatus::APPROVED, $absence->getStatus());
        self::assertSame(DemoDataLoader::TEAM_LEAD_EMAIL, $absence->getDecidedBy()?->getEmail());
        self::assertSame('Viel Spaß!', $absence->getDecisionComment());
    }

    public function testTeamLeadCannotDecideForeignTeam(): void
    {
        $pending = $this->pendingOf(DemoDataLoader::EMPLOYEE_EMAIL);

        // Lead of the other team gets the dashboard without the request …
        $this->loginAs(DemoDataLoader::OTHER_TEAM_LEAD_EMAIL);
        $crawler = $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter(sprintf('#pending-%d', $pending->getId())));

        // … and a forged POST (even with a valid token) is denied by the voter.
        $token = $crawler->filter('form[action^="/absences/"] input[name=_token]');
        $this->client->request('POST', sprintf('/absences/%d/decide', $pending->getId()), [
            '_token' => $token->count() ? $token->first()->attr('value') : 'x',
            'decision' => 'approve',
        ]);
        self::assertResponseStatusCodeSame(403);

        $this->em->clear();
        self::assertSame(AbsenceStatus::REQUESTED, $this->em->getRepository(Absence::class)->find($pending->getId())->getStatus());
    }

    public function testEmployeeCannotDecide(): void
    {
        $pending = $this->pendingOf('jonas.becker@nordlicht.example');
        $this->loginAs(DemoDataLoader::EMPLOYEE_EMAIL);
        $this->client->request('POST', sprintf('/absences/%d/decide', $pending->getId()), ['decision' => 'approve']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testOverlapIsRejected(): void
    {
        $existing = $this->pendingOf(DemoDataLoader::EMPLOYEE_EMAIL);
        $this->loginAs(DemoDataLoader::EMPLOYEE_EMAIL);
        $crawler = $this->client->request('GET', '/absences/new');
        $typeId = $crawler->filter('select[name="absence_request_form[type]"] option')->eq(1)->attr('value');

        $this->client->submitForm('Beantragen', [
            'absence_request_form[type]' => $typeId,
            'absence_request_form[startDate]' => $existing->getStartDate()->format('Y-m-d'),
            'absence_request_form[endDate]' => $existing->getEndDate()->format('Y-m-d'),
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'bereits eine aktive Abwesenheit');
    }

    public function testSicknessIsApprovedImmediatelyAndHiddenFromColleagues(): void
    {
        $this->loginAs(DemoDataLoader::EMPLOYEE_EMAIL);
        $this->client->request('GET', '/calendar');
        self::assertResponseIsSuccessful();
        // Jonas is sick today – colleagues only see "abwesend"
        $row = $this->client->getCrawler()->filter('tbody tr')->reduce(static fn ($tr) => str_contains($tr->text(), 'Jonas Becker'));
        self::assertStringContainsString('abwesend', $row->html());
        self::assertStringNotContainsString('Krankheit', $row->html());
        self::assertStringNotContainsString('Grippe', $row->html());

        // The team lead sees the type
        $this->loginAs(DemoDataLoader::TEAM_LEAD_EMAIL);
        $this->client->request('GET', '/calendar');
        $row = $this->client->getCrawler()->filter('tbody tr')->reduce(static fn ($tr) => str_contains($tr->text(), 'Jonas Becker'));
        self::assertStringContainsString('Krankheit', $row->html());
    }

    private function pendingOf(string $email): Absence
    {
        $absence = $this->em->getRepository(Absence::class)->findOneBy([
            'user' => $this->user($email),
            'status' => AbsenceStatus::REQUESTED,
        ]);
        self::assertNotNull($absence);

        return $absence;
    }
}
