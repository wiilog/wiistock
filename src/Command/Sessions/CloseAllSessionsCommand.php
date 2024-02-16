<?php
// Every deployment

namespace App\Command\Sessions;


use App\Service\SessionHistoryRecordService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(
    name: 'app:sessions:close:all',
    description: 'Close sessions History Records and Sessions.'
)]
class CloseAllSessionsCommand extends Command {
    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public SessionHistoryRecordService $sessionHistoryRecordService;

    public function execute(InputInterface $input, OutputInterface $output): int {
        $this->entityManager->getConnection()->executeQuery('DELETE FROM user_session');
        $this->sessionHistoryRecordService->closeInactiveSessions($this->entityManager);
        return 0;
    }
}
