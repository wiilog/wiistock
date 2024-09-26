<?php


namespace App\Service;

use App\Entity\Pack;
use App\Entity\Tracking\TrackingDelayRecord;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Contracts\Service\Attribute\Required;

class TrackingDelayRecordService {

    #[Required]
    public PackService $packService;

    #[Required]
    public EntityManagerInterface $entityManager;

    public function getDataForDatatable(InputBag $params, Pack $pack): array {
        $trackingDelayRecordRepository = $this->entityManager->getRepository(TrackingDelayRecord::class);
        $queryResult = $trackingDelayRecordRepository->findByFiltersAndPack($params, $pack);

        $rows = [];
        foreach ($queryResult['data'] as $trackingDelayRecord) {
            $rows[] = $trackingDelayRecord;
        }

        return [
            'data' => $rows,
            'recordsTotal' => $queryResult['total'],
            'recordsFiltered' => $queryResult['count'],
        ];
    }
}
