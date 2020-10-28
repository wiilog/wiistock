<?php

namespace App\Command;

use App\Entity\Alert;
use App\Entity\Article;
use App\Entity\ParametrageGlobal;
use App\Helper\Stream;
use App\Service\AlertService;
use App\Service\MailerService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment;

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
