<?php
// At 20:00
// 0 20 * * *

namespace App\Command\Cron;

use App\Service\AlertService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: GenerateAlertsCommand::COMMAND_NAME,
    description: 'Génère les alertes pour les dates de péremption'
)]
class GenerateAlertsCommand extends Command {
    public const COMMAND_NAME = 'app:generate:alerts';

    private EntityManagerInterface $manager;
    private AlertService $service;

    public function __construct(EntityManagerInterface $manager, AlertService $service) {
        parent::__construct();

        $this->manager = $manager;
        $this->service = $service;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $this->service->generateAlerts($this->manager);
        return 0;
    }

}
