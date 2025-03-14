<?php
// At every minute
// * * * * *

namespace App\Command\Cron\ScheduledTask;

use App\Entity\ScheduledTask\InventoryMissionPlan;
use App\Service\InvMissionService;
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
    name: ScheduleInventoryMissionCommand::COMMAND_NAME,
    description: 'This command executes scheduled export.'
)]
class ScheduleInventoryMissionCommand extends Command
{
    public const COMMAND_NAME = 'app:inventory:generate-mission';

    #[Required]
    public EntityManagerInterface $em;

    #[Required]
    public ScheduledTaskService $scheduledTaskService;

    #[Required]
    public InvMissionService $invMissionService;

    protected function execute(InputInterface $input, OutputInterface $output): int {
        return $this->scheduledTaskService->launchScheduledTasks(
            $this->getEntityManager(),
            InventoryMissionPlan::class,
            function (InventoryMissionPlan $inventoryMissionPlan, DateTime $taskExecution) {
                $this->invMissionService->generateMission($this->getEntityManager(), $inventoryMissionPlan, $taskExecution);
            }
        );
    }

    private function getEntityManager(): EntityManagerInterface {
        return $this->em->isOpen()
            ? $this->em
            : new EntityManager($this->em->getConnection(), $this->em->getConfiguration());
    }

}
