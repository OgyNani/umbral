<?php

namespace App\Command;

use App\Entity\Character;
use App\Repository\CharacterRepository;
use App\Service\HealingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Psr\Log\LoggerInterface;

#[AsCommand(
    name: 'app:healing:process',
    description: 'Process healing for all characters in the background',
)]
class HealingCommand extends Command
{
    private CharacterRepository $characterRepository;
    private HealingService $healingService;
    private LoggerInterface $logger;

    public function __construct(
        CharacterRepository $characterRepository,
        HealingService $healingService,
        LoggerInterface $logger
    ) {
        parent::__construct();
        $this->characterRepository = $characterRepository;
        $this->healingService = $healingService;
        $this->logger = $logger;
    }

    protected function configure(): void
    {
        $this
            ->addOption('interval', 'i', InputOption::VALUE_OPTIONAL, 'Interval between healing cycles in seconds', 10)
            ->addOption('daemon', 'd', InputOption::VALUE_NONE, 'Run as a daemon (continuous loop)')
            ->addOption('single', 's', InputOption::VALUE_NONE, 'Run once and exit');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $interval = (int)$input->getOption('interval');
        $isDaemon = $input->getOption('daemon');
        $isSingle = $input->getOption('single');

        if (!$isDaemon && !$isSingle) {
            $io->error('You must specify either --daemon or --single option');
            return Command::FAILURE;
        }

        $io->success('Starting healing process');
        $io->info(sprintf('Healing interval: %d seconds', $interval));

        if ($isDaemon) {
            $io->info('Running in daemon mode (press Ctrl+C to stop)');
            $this->runDaemon($io, $interval);
        } else {
            $io->info('Running in single mode');
            $this->processAllCharacters($io);
        }

        return Command::SUCCESS;
    }

    private function runDaemon(SymfonyStyle $io, int $interval): void
    {
        while (true) {
            $startTime = microtime(true);
            
            $this->processAllCharacters($io);
            
            $elapsedTime = microtime(true) - $startTime;
            $sleepTime = max(0, $interval - $elapsedTime);
            
            if ($sleepTime > 0) {
                $io->info(sprintf('Sleeping for %.2f seconds', $sleepTime));
                usleep($sleepTime * 1000000);
            }
        }
    }

    private function processAllCharacters(SymfonyStyle $io): void
    {
        $characters = $this->characterRepository->findAll();
        $io->info(sprintf('Processing healing for %d characters', count($characters)));
        
        $healedCount = 0;
        
        foreach ($characters as $character) {
            if ($character->getHp() < $character->getMaxHp()) {
                // Use character's Telegram chat ID as the unique identifier
                $chatId = $character->getUser()->getTelegramId();
                
                // Process healing for this character
                $oldHp = $character->getHp();
                $this->healingService->processHealing($chatId, $character);
                
                // Check if HP changed
                if ($character->getHp() > $oldHp) {
                    $healedCount++;
                    $io->info(sprintf(
                        'Healed character %s: %d -> %d HP',
                        $character->getName(),
                        $oldHp,
                        $character->getHp()
                    ));
                }
            }
        }
        
        $io->success(sprintf('Healing cycle completed. Healed %d characters', $healedCount));
    }
}
