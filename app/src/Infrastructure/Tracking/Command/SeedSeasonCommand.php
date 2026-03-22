<?php

declare(strict_types=1);

namespace App\Infrastructure\Tracking\Command;

use App\Application\Tracking\Service\SeasonSeedService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'tracking:seed-season',
    description: 'Seeds all teams and fixtures for a competition from football-data.org',
)]
class SeedSeasonCommand extends Command
{
    public function __construct(private readonly SeasonSeedService $seedService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('competition', InputArgument::REQUIRED, 'Competition code (e.g. PD, PL, BL1)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $code = $input->getArgument('competition');

        $io->title(sprintf('Seeding season for competition: %s', $code));

        $this->seedService->seed($code);

        $io->success(sprintf('Season seeded successfully for %s.', $code));

        return Command::SUCCESS;
    }
}
