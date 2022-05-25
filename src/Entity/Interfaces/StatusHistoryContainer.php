<?php

namespace App\Entity\Interfaces;

use App\Entity\StatusHistory;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use WiiCommon\Helper\Stream;

abstract class StatusHistoryContainer {

    /**
     * @return Collection<int, StatusHistory>
     */
    public abstract function getStatusHistory(string $order = Criteria::ASC): Collection;

    public abstract function addStatusHistory(StatusHistory $statusHistory): self;

    public abstract function removeStatusHistory(StatusHistory $statusHistory): self;

    public function getLastStatusHistory(array $statusCodes): array|null
    {
        return Stream::from($this->getStatusHistory())
            ->filter(fn(StatusHistory $history) => in_array($history->getStatus()->getCode(), $statusCodes))
            ->sort(fn(StatusHistory $s1, StatusHistory $s2) => $s2->getId() <=> $s1->getId())
            ->keymap(fn(StatusHistory $history) => [$history->getStatus()->getCode(), $history->getDate()])
            ->toArray();
    }
}
