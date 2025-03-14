<?php
// At every minute
// * * * * *

namespace App\Command\Cron\ScheduledTask;

use App\Entity\ScheduledTask\PurchaseRequestPlan;
use App\Service\PurchaseRequestPlanService;
use App\Service\ScheduledTaskService;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(
    name: ScheduledPurchaseRequestCommand::COMMAND_NAME,
    description: 'This command executes scheduled purchase resquests.'
)]
class ScheduledPurchaseRequestCommand extends Command {

    public const COMMAND_NAME = "app:launch:scheduled-purchase-request";

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public PurchaseRequestPlanService $purchaseRequestPlanService;

    #[Required]
    public ScheduledTaskService $scheduledTaskService;

    protected function execute(InputInterface $input, OutputInterface $output): int {
        return $this->scheduledTaskService->launchScheduledTasks(
            $this->getEntityManager(),
            PurchaseRequestPlan::class,
            function (PurchaseRequestPlan $purchaseRequestPlan, DateTime $taskExecution) {
                $this->purchaseRequestPlanService->treatRequestPlan($this->getEntityManager(), $purchaseRequestPlan, $taskExecution);
            }
        );
    }

    private function getEntityManager(): EntityManagerInterface {
        return $this->entityManager->isOpen()
            ? $this->entityManager
            : new EntityManager($this->entityManager->getConnection(), $this->entityManager->getConfiguration());
    }
}
