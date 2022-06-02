<?php
// Every day at 23:00
// 00 23 * * *

namespace App\Command;

use App\Service\Transport\TransportRoundService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

class TransportRoundUploadFTPCommand extends Command {

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public TransportRoundService $transportRoundService;

    protected function configure() {
		$this->setName('app:transports:export-rounds');
		$this->setDescription('This commands upload export rounds to a SFTP Server.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $this->transportRoundService->launchCSVExport($this->entityManager);
        return 0;
    }
}
