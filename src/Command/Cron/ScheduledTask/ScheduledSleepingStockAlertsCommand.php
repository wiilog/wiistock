<?php
// At every minute
// * * * * *

namespace App\Command\Cron\ScheduledTask;

use App\Entity\ScheduledTask\Export;
use App\Entity\ScheduledTask\SleepingStockPlan;
use App\Service\ScheduledExportService;
use App\Service\ScheduledTaskService;
use App\Service\SleepingStockPlanService;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;


#[AsCommand(
    name: ScheduledSleepingStockAlertsCommand::COMMAND_NAME,
    description: 'This command executes scheduled sleeping stock alerts.'
)]
class ScheduledSleepingStockAlertsCommand extends Command {
    public const COMMAND_NAME = 'app:launch:sleeping-stock-alerts';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ScheduledTaskService   $scheduledTaskService,
        private SleepingStockPlanService $sleepingStockPlanService
    ){
        parent::__construct();
    }

    protected function execute(InputInterface  $input,
                               OutputInterface $output): int {
        return $this->scheduledTaskService->launchScheduledTasks(
            $this->getEntityManager(),
            SleepingStockPlan::class,
            function (SleepingStockPlan $sleepingStockPlan, DateTime $taskExecution): void {
                $this->sleepingStockPlanService->triggerSleepingStockPlan(
                    $this->getEntityManager(),
                    $sleepingStockPlan,
                    $taskExecution);
            }
        );
    }

    private function getEntityManager(): EntityManagerInterface {
        return $this->entityManager->isOpen()
            ? $this->entityManager
            : new EntityManager($this->entityManager->getConnection(), $this->entityManager->getConfiguration());
    }
}
