<?php

namespace App\Command;

use App\Service\NBPService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use DateTime;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

class UpdateNBPRatesCommand extends Command
{

    private NBPService $NBPService;

    public function __construct(
        NBPService $NBPService
    )
    {
        $this->NBPService = $NBPService;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('nbp:rates:update')
            ->setDescription('Update NBP Currency rates')
            ->setHelp('Update NBP Currency rates');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $store = new FlockStore();
        $factory = new LockFactory($store);
        $startTime = new DateTime('now');
        $lock = $factory->createLock('UpdateNBPRatesCommand');

        if (!$lock->acquire()) {
            $io->writeln([
                " > UpdateNBPRatesCommand blocked by another process - " . $startTime->format("Y-m-d H:i:s") . PHP_EOL
            ]);
            return Command::INVALID;
        }

        $io = new SymfonyStyle($input, $output);
        $io->writeln([
            " > Started processing NBP Currency rates " . $startTime->format("Y-m-d H:i:s")
        ]);

        $io->write($this->NBPService->updateCurrencyRates() . PHP_EOL);

        $endTime = new DateTime('now');
        $io->writeln([
            " > Ended processing NBP Currency rates. " . $endTime->format("Y-m-d H:i:s")
        ]);

        $lock->release();

        return Command::SUCCESS;
    }

}