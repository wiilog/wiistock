<?php

namespace App\Entity\Interfaces;

use App\Entity\StatusHistory;
use App\Entity\Statut;
use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use WiiCommon\Helper\Stream;

#[ORM\MappedSuperclass()]
abstract class StatusHistoryContainer {
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $lastPartialStatusDate = null;

    public function getLastPartialStatusDate(): ?DateTime
    {
        return $this->lastPartialStatusDate;
    }

    public function setLastPartialStatusDate(?DateTime $lastPartialStatusDate): self
    {
        $this->lastPartialStatusDate = $lastPartialStatusDate;

        return $this;
    }

    /**
     * @return Collection<int, StatusHistory>
     */
    public abstract function getStatusHistory(string $order = Criteria::ASC): Collection;

    public abstract function addStatusHistory(StatusHistory $statusHistory): self;

    public abstract function removeStatusHistory(StatusHistory $statusHistory): self;

    public abstract function clearStatusHistory(): self;

    public abstract function setStatus(Statut $status): self;

    public function getLastStatusHistory(array $statusCodes): array|null {
        return Stream::from($this->getStatusHistory())
            ->filter(fn(StatusHistory $history) => in_array($history->getStatus()->getCode(), $statusCodes))
            ->sort(fn(StatusHistory $s1, StatusHistory $s2) => $s2->getId() <=> $s1->getId())
            ->keymap(fn(StatusHistory $history) => [$history->getStatus()->getCode(), $history->getDate()])
            ->toArray();
    }

}
