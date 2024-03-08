<?php

namespace App\Command\Cron;

use App\Service\IOT\IOTService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(
    name: KooveaHubsCommand::COMMAND_NAME,
    description: 'Recupere les données des hubs Koovea'
)]
class KooveaHubsCommand extends Command {

    public const COMMAND_NAME = 'app:run:koovea:hub';

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public IOTService $iotService;

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $this->iotService->runKooveaJOB($this->entityManager, IOTService::KOOVEA_HUB);
        return 0;
    }

}
