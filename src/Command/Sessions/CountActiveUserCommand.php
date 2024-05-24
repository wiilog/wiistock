<?php
// Every deployment

namespace App\Command\Sessions;


use App\Entity\SessionHistoryRecord;
use App\Service\SessionHistoryRecordService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(
    name: 'app:sessions:countActiveUsers',
    description: 'Count active users.'
)]
class CountActiveUserCommand extends Command {
    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public SessionHistoryRecordService $sessionHistoryRecordService;

    protected function configure(): void {
        $this
            ->addArgument('from', InputArgument::REQUIRED, 'From date')
            ->addArgument('to', InputArgument::REQUIRED, 'To date');
    }

    /*
     * Example of usage:
     * php bin/console app:sessions:countActiveUsers 2022-01-01 2022-01-31
     */

    public function execute(InputInterface $input, OutputInterface $output): int {
        $sessionHistoryRecordRepository = $this->entityManager->getRepository(SessionHistoryRecord::class);

        try {
            $from = new \DateTime($input->getArgument('from'));
            $to = new \DateTime($input->getArgument('to'));
        } catch (\Exception $e) {
            $output->writeln('Invalid date format');
            return 1;
        }

        echo $sessionHistoryRecordRepository->countActiveUsers($from, $to) . "\n" ;

        return 0;
    }
}
