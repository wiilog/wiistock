<?php

namespace App\Command;

use App\Entity\IOT\Pairing;
use App\Service\IOT\IOTService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class CheckPairingValidityCommand extends Command {

    protected static $defaultName = "app:iot:pairing-validity";

    /** @Required */
    public EntityManagerInterface $entityManager;

    protected function configure() {
        $this->setDescription("Deactivates pairing that reached the end date");
    }

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
