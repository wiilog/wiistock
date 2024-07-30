<?php

namespace App\Entity\OperationHistory;

use App\Entity\Pack;
use App\Entity\Traits\AttachmentTrait;
use App\Repository\LogisticUnitHistoryRecordRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LogisticUnitHistoryRecordRepository::class)]
class LogisticUnitHistoryRecord extends OperationHistory {

    use AttachmentTrait;

    #[ORM\ManyToOne(inversedBy: 'logisticUnitHistoryRecords')]
    private ?Pack $pack = null;

    public function __construct() {
        $this->attachments = new ArrayCollection();
    }

    public function getPack(): ?Pack
    {
        return $this->pack;
    }

    public function setPack(?Pack $pack): self
    {
        if($this->pack && $this->pack !== $pack){
            $this->pack->removeLogisticUnitHistoryRecord($this);
        }

        $this->pack = $pack;
        $pack?->addLogisticUnitHistoryRecord($this);

        return $this;
    }
}
