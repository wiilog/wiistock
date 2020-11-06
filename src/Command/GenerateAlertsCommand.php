<?php

namespace App\Command;

use App\Service\AlertService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateAlertsCommand extends Command {

    private $manager;
    private $service;

    public function __construct(EntityManagerInterface $manager, AlertService $service) {
        parent::__construct();

        $this->manager = $manager;
        $this->service = $service;
    }

    protected function configure() {
        $this->setName("app:generate:alerts");
        $this->setDescription("Génère les alertes pour les dates de péremption");
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->service->generateAlerts($this->manager);
    }

}
