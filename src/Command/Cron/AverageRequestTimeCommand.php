<?php
// At 20:00
// 0 20 * * *

namespace App\Command\Cron;

use App\Entity\AverageRequestTime;
use App\Entity\Collecte;
use App\Entity\DeliveryRequest\Demande;
use App\Entity\Dispatch;
use App\Entity\Handling;
use App\Entity\TransferRequest;
use App\Entity\Type\Type;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WiiCommon\Helper\Stream;


#[AsCommand(
    name: AverageRequestTimeCommand::COMMAND_NAME,
    description: 'Feeds the average request processing time'
)]
class AverageRequestTimeCommand extends Command {
    public const COMMAND_NAME = 'app:feed:average:requests';

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager) {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $demandeRepository = $this->entityManager->getRepository(Demande::class);
        $collecteRepository = $this->entityManager->getRepository(Collecte::class);
        $handlingRepository = $this->entityManager->getRepository(Handling::class);
        $dispatchRepository = $this->entityManager->getRepository(Dispatch::class);
        $transferRequestRepository = $this->entityManager->getRepository(TransferRequest::class);
        $typeRepository = $this->entityManager->getRepository(Type::class);

        $values = array_merge(
            $demandeRepository->getProcessingTime(),
            $handlingRepository->getProcessingTime(),
            $collecteRepository->getProcessingTime(),
            $dispatchRepository->getProcessingTime(),
            $transferRequestRepository->getProcessingTime()
        );

        $types = [];
        $typeMeters = [];
        foreach($values as $value) {
            $types[$value["type"]] = true;
            $typeMeters[$value["type"]] = [
                "total" => $value["total"],
                "count" => $value["count"],
            ];
        }

        $types = Stream::from($typeRepository->findBy(["id" => array_keys($types)]))
            ->keymap(function($type) {
                return [$type->getId(), $type];
            })
            ->toArray();

        foreach ($typeMeters as $typeId => $total) {
            $average = (int)floor($total["total"] / $total["count"]);

            $type = $types[$typeId];
            $averageTime = $type->getAverageRequestTime();

            if (!$averageTime) {
                $averageTime = new AverageRequestTime();
                $averageTime->setType($type);
                $this->entityManager->persist($averageTime);
            }

            $averageTime->setAverage($average);
        }

        $this->entityManager->flush();

        return 0;
    }
}
