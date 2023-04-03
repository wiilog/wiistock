<?php

namespace App\Service;

use App\Entity\Arrivage;
use App\Entity\Chauffeur;
use App\Entity\Dispatch;
use App\Entity\Reception;
use App\Entity\Transporteur;
use App\Entity\TruckArrival;
use App\Entity\Urgence;
use Doctrine\ORM\EntityManagerInterface;


class CarrierService {
    public function getUserOwnership(EntityManagerInterface $entityManager,
                                     Transporteur           $transporteur): array {
        $truckArrivalRepository = $entityManager->getRepository(TruckArrival::class);
        $arrivalRepository = $entityManager->getRepository(Arrivage::class);
        $driverRepository = $entityManager->getRepository(Chauffeur::class);
        $dispatchRepository = $entityManager->getRepository(Dispatch::class);
        $receptionRepository = $entityManager->getRepository(Reception::class);
        $emergencyRepository = $entityManager->getRepository(Urgence::class);

        return [
            'arrivage(s) camion' => $truckArrivalRepository->count(['carrier' => $transporteur]),
            'arrivage(s) UL' => $arrivalRepository->count(['transporteur' => $transporteur]),
            'chauffeur(s)' => $driverRepository->count(['transporteur' => $transporteur]),
            'acheminement(s)' => $dispatchRepository->count(['carrier' => $transporteur]),
            'reception(s)' => $receptionRepository->count(['transporteur' => $transporteur]),
            'urgence(s)' => $emergencyRepository->count(['carrier' => $transporteur])
        ];
    }
}
