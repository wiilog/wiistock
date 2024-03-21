<?php
// At every 10th minute
// */10 * * * *

namespace App\Command\Cron;

use App\Entity\IOT\Pairing;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(
    name: CheckPairingValidityCommand::COMMAND_NAME,
    description: 'Deactivates pairing that reached the end date'
)]
class CheckPairingValidityCommand extends Command {

    public const COMMAND_NAME="app:iot:pairing-validity";

    #[Required]
    public EntityManagerInterface $entityManager;

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $pairingRepository = $this->entityManager->getRepository(Pairing::class);
        $pairings = $pairingRepository->findExpiredActive();
        foreach($pairings as $pairing) {
            $pairing->setActive(false);
        }

        $this->entityManager->flush();

        return 0;
    }
}
