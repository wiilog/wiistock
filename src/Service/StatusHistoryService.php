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
                                 Statut                          $status,
                                 ?DateTime                       $date = null): StatusHistory {
        $history = (new StatusHistory())
            ->setStatus($status)
            ->setDate($date ?? new DateTime());

        $entity->setStatus($status);

        if ($entity instanceof TransportRequest) {
            $method = "setTransportRequest";
        }
        else if ($entity instanceof TransportOrder) {
            $method = "setTransportOrder";
        }
        else {
            throw new RuntimeException("Unsupported entity type");
        }

        $latestStatus = $entity->getStatusHistory()->last();
        if ($latestStatus && $latestStatus->getStatus()->getId() === $status->getId()) {
            $latestStatus->setDate($date ?? new DateTime());
            return $latestStatus;
        }
        else {
            $history->{$method}($entity);
        }

        $entityManager->persist($history);

        return $history;
    }

}
