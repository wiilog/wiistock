<?php

namespace App\Service;

use App\Entity\Interfaces\StatusHistoryContainer;
use App\Entity\StatusHistory;
use App\Entity\Statut;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;

class StatusHistoryService {

    public function updateStatus(EntityManagerInterface $entityManager,
                                 StatusHistoryContainer $historyContainer,
                                 Statut                 $status,
                                 ?DateTime              $date = null,
                                 array                  $options = []): StatusHistory {

        $forceCreation = $options['forceCreation'] ?? true;
        $setStatus = $options['setStatus'] ?? true;

        if ($forceCreation) {
            $record = $this->createStatusHistory($historyContainer, $status);
            $entityManager->persist($record);
        }
        else {
            $record = $this->getPreviousRecord($historyContainer, $status);
            if (!isset($record)) {
                $record = $this->createStatusHistory($historyContainer, $status);
                $entityManager->persist($record);
            }
        }

        $record->setDate($date ?? new DateTime());

        if ($setStatus) {
            $historyContainer->setStatus($status);
        }

        return $record;
    }

    private function createStatusHistory(StatusHistoryContainer $historyContainer,
                                         Statut                 $status): StatusHistory {

        $history = (new StatusHistory())
            ->setStatus($status);

        $historyContainer->addStatusHistory($history);

        return $history;
    }

    private function getPreviousRecord(StatusHistoryContainer $historyContainer,
                                       Statut                 $status): ?StatusHistory {
        $history = $historyContainer->getStatusHistory(Criteria::DESC);
        $record = null;
        $currentRecord = $history->current();
        $newStatusId = $status->getId();

        while (!isset($record) && $currentRecord) {
            $currentStatusId = $currentRecord->getStatus()->getId();
            if ($newStatusId === $currentStatusId) {
                $record = $currentRecord;
            }
            else {
                $currentRecord = $history->next();
            }
        }

        return $record;
    }

}
