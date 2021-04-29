<?php
// At 20:00
// 0 20 * * *

namespace App\Command;

use App\Entity\AverageRequestTime;
use App\Entity\Collecte;
use App\Entity\Demande;
use App\Entity\Dispatch;
use App\Entity\Handling;
use App\Entity\TransferRequest;
use App\Entity\Type;
use WiiCommon\Helper\Stream;
use App\Service\DateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class AverageRequestTimeCommand extends Command {

    protected static $defaultName = "app:feed:average:requests";

    private $entityManager;
    private $dateService;

    public function __construct(EntityManagerInterface $entityManager, DateService $dateService) {
        parent::__construct(self::$defaultName);
        $this->entityManager = $entityManager;
        $this->dateService = $dateService;
    }

    protected function configure() {
        $this->setDescription("Feeds the average request processing time");
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
