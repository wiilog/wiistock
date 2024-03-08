<?php
// Every 5 minutes
// */5 * * * *

namespace App\Command\Cron;

use App\Entity\Wiilock;
use App\Service\SessionHistoryRecordService;
use App\Service\WiilockService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(
    name: CloseInactiveSessionsCommand::COMMAND_NAME,
    description: "Close inactive sessions History Records"
)]
class CloseInactiveSessionsCommand extends Command
{
    public const COMMAND_NAME= "app:sessions:close:inactives";

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public SessionHistoryRecordService $sessionHistoryRecordService;

    #[Required]
    public WiilockService $wiilockService;

    public function execute(InputInterface $input, OutputInterface $output): int {
        $this->wiilockService->toggleFeedingCommand($this->entityManager, false, Wiilock::INACTIVE_SESSIONS_CLEAN_KEY);
        $this->entityManager->flush();
        $this->sessionHistoryRecordService->closeInactiveSessions($this->entityManager);
        return 0;
    }
}
