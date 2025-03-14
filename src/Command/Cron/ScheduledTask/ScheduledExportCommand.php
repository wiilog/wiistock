<?php
// At every minute
// * * * * *

namespace App\Command\Cron\ScheduledTask;

use App\Entity\ScheduledTask\Export;
use App\Service\ScheduledExportService;
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
    name: ScheduledExportCommand::COMMAND_NAME,
    description: 'This command executes scheduled export.'
)]
class ScheduledExportCommand extends Command
{
    public const COMMAND_NAME = 'app:launch:scheduled-exports';

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public ScheduledExportService $exportService;

    #[Required]
    public ScheduledTaskService $scheduledTaskService;

    protected function execute(InputInterface  $input,
                               OutputInterface $output): int {
        return $this->scheduledTaskService->launchScheduledTasks(
            $this->getEntityManager(),
            Export::class,
            function (Export $export, DateTime $taskExecution) {
                $this->exportService->export($this->getEntityManager(), $export, $taskExecution);
            }
        );
    }

    private function getEntityManager(): EntityManagerInterface {
        return $this->entityManager->isOpen()
            ? $this->entityManager
            : new EntityManager($this->entityManager->getConnection(), $this->entityManager->getConfiguration());
    }

}
