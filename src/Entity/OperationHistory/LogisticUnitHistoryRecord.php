<?php

namespace App\Entity\OperationHistory;

use App\Entity\Emplacement;
use App\Entity\Tracking\Pack;
use App\Entity\Traits\AttachmentTrait;
use App\Repository\LogisticUnitHistoryRecordRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LogisticUnitHistoryRecordRepository::class)]
class LogisticUnitHistoryRecord extends OperationHistory {

    use AttachmentTrait;

    #[ORM\ManyToOne(targetEntity: Pack::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Pack $pack = null;

    #[ORM\ManyToOne]
    private ?Emplacement $location = null;

    public function __construct() {
        $this->attachments = new ArrayCollection();
    }

    public function getPack(): ?Pack {
        return $this->pack;
    }

    public function setPack(?Pack $pack): self {
        $this->pack = $pack;

        return $this;
    }

    public function getLocation(): ?Emplacement {
        return $this->location;
    }

    public function setLocation(?Emplacement $location): self {
        $this->location = $location;

        return $this;
    }
}
