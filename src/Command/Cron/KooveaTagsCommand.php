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
    name: KooveaTagsCommand::COMMAND_NAME,
    description: 'Recupere les données des tags Koovea'
)]
class KooveaTagsCommand extends Command {

    public const COMMAND_NAME = 'app:run:koovea:tag';

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public IOTService $iotService;

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $this->iotService->runKooveaJOB($this->entityManager, IOTService::KOOVEA_TAG);
        return 0;
    }

}
