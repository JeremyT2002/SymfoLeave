<?php

declare(strict_types=1);

namespace App\Command;

use App\Demo\DemoDataLoader;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:demo:reset', description: 'Setzt die Datenbank neu auf und lädt die Demo-Daten.')]
final class DemoResetCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DemoDataLoader $loader,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Drop everything (incl. migration table), then rebuild via migrations.
        (new SchemaTool($this->em))->dropDatabase();

        $migrate = new ArrayInput(['--allow-no-migration' => true]);
        $migrate->setInteractive(false);
        $code = $this->getApplication()?->find('doctrine:migrations:migrate')->run($migrate, new NullOutput()) ?? Command::FAILURE;
        if ($code !== Command::SUCCESS) {
            $io->error('Migrationen fehlgeschlagen.');

            return Command::FAILURE;
        }

        $this->em->clear();
        $this->loader->load($this->em);
        $io->success('Demo-Daten wurden neu geladen.');

        return Command::SUCCESS;
    }
}
