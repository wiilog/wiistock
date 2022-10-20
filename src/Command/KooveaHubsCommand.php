<?php

namespace App\Command;

use App\Service\IOT\IOTService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class KooveaHubsCommand extends Command {

    private $entityManager;
    private $iotService;

    public function __construct(EntityManagerInterface $entityManager, IOTService $iotService) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->iotService = $iotService;
    }

    protected function configure() {
		$this->setName('app:run:koovea:hub');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->iotService->runKooveaJOB($this->entityManager, IOTService::KOOVEA_HUB);
        return 0;
    }

}
