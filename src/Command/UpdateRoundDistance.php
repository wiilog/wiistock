<?php
// Every day at 23:00
// 00 23 * * *

namespace App\Command;

use App\Entity\Transport\TransportRound;
use App\Service\Transport\TransportRoundService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

class UpdateRoundDistance extends Command {

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public TransportRoundService $transportRoundService;

    protected function configure() {
        $this
            ->setName('app:update:round:distance')
            ->addArgument('rounds', InputArgument::IS_ARRAY, '"rounds to update"');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $rounds = $this->entityManager->getRepository(TransportRound::class);

        $ids = $input->getArgument('rounds');

        $entities = $rounds->findBy([
            'id' => $ids
        ]);

        foreach ($entities as $entity) {
            dump($entity->getId() . ' - ' . ($this->transportRoundService->calculateRoundRealDistance($entity) / 1000) . ' kms');
        }

        return 0;
    }
}
