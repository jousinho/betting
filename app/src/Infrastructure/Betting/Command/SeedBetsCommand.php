<?php

declare(strict_types=1);

namespace App\Infrastructure\Betting\Command;

use App\Application\Betting\Service\BetGeneratorService;
use App\Application\Betting\Service\BetSettlementService;
use App\Domain\Betting\Repository\BetRepositoryInterface;
use App\Domain\Tracking\Repository\CompetitionRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'betting:seed-bets', description: 'Generate and settle historical bets for a competition')]
class SeedBetsCommand extends Command
{
    public function __construct(
        private readonly CompetitionRepositoryInterface $competitionRepository,
        private readonly BetRepositoryInterface $betRepository,
        private readonly BetGeneratorService $generatorService,
        private readonly BetSettlementService $settlementService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('competition', InputArgument::REQUIRED, 'Competition code (e.g. PD)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $code = $input->getArgument('competition');

        $competition = $this->competitionRepository->findByCode($code);

        if ($competition === null) {
            $io->error(sprintf('Competition "%s" not found. Run tracking:seed-season first.', $code));
            return Command::FAILURE;
        }

        $io->section('Clearing existing bets...');
        $this->betRepository->clearByCompetition($competition);
        $io->text('Done.');

        $io->section('Generating historical bets (finished matches)...');
        $this->generatorService->generateHistorical($competition);
        $io->text('Done.');

        $io->section('Settling historical bets...');
        $this->settlementService->settleAll($competition);
        $io->text('Done.');

        $io->section('Generating bets for upcoming matches...');
        $this->generatorService->generate($competition);
        $io->text('Done.');

        $io->success(sprintf('Bets seeded for "%s".', $code));

        return Command::SUCCESS;
    }
}
