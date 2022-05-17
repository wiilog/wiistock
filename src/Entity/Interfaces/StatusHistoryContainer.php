<?php

namespace App\Entity\Interfaces;

use App\Entity\StatusHistory;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;

interface StatusHistoryContainer {

    /**
     * @return Collection<int, StatusHistory>
     */
    public function getStatusHistory(string $order = Criteria::ASC): Collection;

    public function addStatusHistory(StatusHistory $statusHistory): self;

    public function removeStatusHistory(StatusHistory $statusHistory): self;
}
