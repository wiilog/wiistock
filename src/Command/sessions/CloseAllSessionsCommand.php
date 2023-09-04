<?php
// Every deployment

namespace App\Command\sessions;


use App\Service\SessionHistoryRecordService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

class CloseAllSessionsCommand extends Command
{
    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public SessionHistoryRecordService $sessionHistoryRecordService;


    public function __construct() {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName("app:sessions:close:all");
        $this->setDescription("Close sessions History Records and Sessions");
    }

    public function execute(InputInterface $input, OutputInterface $output): int {
        $this->entityManager->getConnection()->executeQuery('DELETE FROM user_session');
        $this->sessionHistoryRecordService->closeInactiveSessions($this->entityManager);
        return 0;
    }
}
