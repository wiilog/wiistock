<?php
// Every deployment

namespace App\Command\sessions;


use App\Entity\SessionHistoryRecord;
use App\Service\FormatService;
use App\Service\SessionHistoryRecordService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

class CloseSessionsCommand extends Command
{
    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public SessionHistoryRecordService $sessionHistoryRecordService;

    #[Required]
    public FormatService $formatService;


    public function __construct() {
        parent::__construct();
    }

    protected function configure(): void {
        $this
            ->setName("app:sessions:close")
            ->setDescription("Close sessions History Records")
            ->addArgument('ids', InputArgument::IS_ARRAY, 'Ids of the sessions to close');

    }

    public function execute(InputInterface $input, OutputInterface $output): int {
        $sessionHistoryRecordRepository = $this->entityManager->getRepository(SessionHistoryRecord::class);
        $ids = $input->getArgument('ids');
        $now = new DateTime();
        foreach ($ids as $id) {
            $sessionHistoryRecord = $sessionHistoryRecordRepository->findOneBy(['sessionId' => $id]) ?? $sessionHistoryRecordRepository->findOneBy(['id' => $id]);
            if ($sessionHistoryRecord) {
                $this->sessionHistoryRecordService->closeSessionHistoryRecord($this->entityManager, $sessionHistoryRecord, $now);
            }
            $user =  $this->formatService->user($sessionHistoryRecord->getUser());
            $type = $sessionHistoryRecord->getType()->getLabel();
            $output->writeln(
                "Déconnexion de la session d'id $id, $user a été déconnecté de sa $type"
            );
        }
        return 0;
    }
}
