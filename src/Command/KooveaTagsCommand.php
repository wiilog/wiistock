<?php

namespace App\Command;

use App\Service\IOT\IOTService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:run:koovea:tag',
    description: 'Recupere les données des tags Koovea'
)]
class KooveaTagsCommand extends Command {

    private $entityManager;
    private $iotService;

    public function __construct(EntityManagerInterface $entityManager, IOTService $iotService) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->iotService = $iotService;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $this->iotService->runKooveaJOB($this->entityManager, IOTService::KOOVEA_TAG);
        return 0;
    }

}
