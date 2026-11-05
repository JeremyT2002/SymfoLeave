<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Demo\DemoDataLoader;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AppWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);

        $tool = new SchemaTool($this->em);
        $tool->dropDatabase();
        $tool->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $container->get(DemoDataLoader::class)->load($this->em);
        $this->em->clear();
    }

    protected function user(string $email): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user, "Demo-User $email fehlt");

        return $user;
    }

    protected function loginAs(string $email): User
    {
        $user = $this->user($email);
        $this->client->loginUser($user);

        return $user;
    }
}
