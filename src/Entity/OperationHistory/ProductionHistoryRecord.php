<?php

namespace App\Entity\OperationHistory;

use App\Entity\ProductionRequest;
use App\Entity\StatusHistory;
use App\Entity\Traits\AttachmentTrait;
use App\Repository\ProductionHistoryRecordRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductionHistoryRecordRepository::class)]
class ProductionHistoryRecord extends OperationHistory {

    use AttachmentTrait;

    #[ORM\ManyToOne(targetEntity: ProductionRequest::class, inversedBy: "history")]
    #[ORM\JoinColumn(nullable: true)]
    private ?ProductionRequest $request = null;

    #[ORM\ManyToOne(targetEntity: StatusHistory::class, cascade: ['persist'], inversedBy: 'productionRequestHistory')]
    #[ORM\JoinColumn(nullable: true)]
    private ?StatusHistory $statusHistory = null;

    public function __construct() {
        $this->attachments = new ArrayCollection();
    }

    public function getRequest(): ?ProductionRequest
    {
        return $this->request;
    }

    public function setRequest(?ProductionRequest $request): self
    {
        $this->request = $request;

        return $this;
    }

    public function getStatusHistory(): ?StatusHistory {
        return $this->statusHistory;
    }

    public function setStatusHistory(?StatusHistory $statusHistory): self {
        if($this->statusHistory && $this->statusHistory !== $statusHistory) {
            $this->statusHistory->removeProductionRequestHistory($this);
        }
        $this->statusHistory = $statusHistory;
        $statusHistory?->addProductionRequestHistory($this);

        return $this;
    }
}
