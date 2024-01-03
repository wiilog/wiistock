<?php

namespace App\Entity\OperationHistory;

use App\Entity\ProductionRequest;
use App\Entity\Project;
use App\Entity\Traits\AttachmentTrait;
use App\Repository\Transport\TransportHistoryRecordRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransportHistoryRecordRepository::class)]
class ProductionHistoryRecord extends OperationHistory {

    use AttachmentTrait;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?ProductionRequest $request = null;

    public function getRequest(): ?ProductionRequest
    {
        return $this->request;
    }

    public function setRequest(?ProductionRequest $request): self
    {
        $this->request = $request;

        return $this;
    }
}
