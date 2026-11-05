<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Demo\DemoDataLoader;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/** Dev/Test fixtures – reuse the same loader as app:demo:reset. */
final class AppFixtures extends Fixture
{
    public function __construct(private readonly DemoDataLoader $loader)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $this->loader->load($manager);
    }
}
