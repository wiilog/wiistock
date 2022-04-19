<?php

namespace App\Service;

use App\Entity\StatusHistory;
use App\Entity\Statut;
use App\Entity\Transport\TransportOrder;
use App\Entity\Transport\TransportRequest;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

class StatusHistoryService {

    public function updateStatus(EntityManagerInterface          $entityManager,
                                 TransportRequest|TransportOrder $entity,
                                 Statut                          $status): StatusHistory {
        $history = (new StatusHistory())
            ->setStatus($status)
            ->setDate(new DateTime());

        $entity->setStatus($status);

        if ($entity instanceof TransportRequest) {
            $history->setTransportRequest($entity);
        }
        else if ($entity instanceof TransportOrder) {
            $history->setTransportOrder($entity);
        }
        else {
            throw new RuntimeException('Unavailable entity type');
        }

        $entityManager->persist($history);

        return $history;
    }
}
