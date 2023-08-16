<?php
// Every 5 minutes
// */5  * * * *

namespace App\Command;

use App\Entity\SessionHistoryRecord;
use App\Service\SessionHistoryRecordService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

class CloseInactiveSessionsCommand extends Command
{
    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public SessionHistoryRecordService $sessionHistoryRecordService;


    public function __construct() {
        parent::__construct();
    }

    protected function configure() {
        $this->setName("app:sessions:close:inactives");
        $this->setDescription("Close inactive sessions History Records");
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->sessionHistoryRecordService->closeInactiveSessions($this->entityManager);
        return 0;
    }
}
