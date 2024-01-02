<?php

namespace App\Entity;

use App\Entity\Traits\AttachmentTrait;
use App\Entity\Traits\FreeFieldsManagerTrait;
use App\Repository\ProductionRequestRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductionRequestRepository::class)]
class ProductionRequest
{

    use AttachmentTrait;
    use FreeFieldsManagerTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Type::class)]
    private ?Type $type = null;

    #[ORM\ManyToOne(targetEntity: Statut::class)]
    private ?Statut $status = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $manufacturingOrderNumber = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $emergency = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $expectedAt = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $projectNumber = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $productArticleCode = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $quantity = null;

    #[ORM\ManyToOne(targetEntity: Emplacement::class)]
    private ?Emplacement $dropLocation = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $lineNumber = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\OneToMany(mappedBy: 'productionRequest', targetEntity: StatusHistory::class)]
    private Collection $statusHistory;

    public function __construct() {
        $this->statusHistory = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?Type {
        return $this->type;
    }

    public function setType(?Type $type): self {
        $this->type = $type;

        return $this;
    }

    public function getStatus(): ?Statut
    {
        return $this->status;
    }

    public function setStatus(?Statut $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getManufacturingOrderNumber(): ?string
    {
        return $this->manufacturingOrderNumber;
    }

    public function setManufacturingOrderNumber(?string $manufacturingOrderNumber): self
    {
        $this->manufacturingOrderNumber = $manufacturingOrderNumber;

        return $this;
    }

    public function getEmergency(): ?string
    {
        return $this->emergency;
    }

    public function setEmergency(?string $emergency): self
    {
        $this->emergency = $emergency;

        return $this;
    }

    public function getExpectedAt(): ?DateTime
    {
        return $this->expectedAt;
    }

    public function setExpectedAt(?DateTime $expectedAt): self
    {
        $this->expectedAt = $expectedAt;

        return $this;
    }

    public function getProjectNumber(): ?string
    {
        return $this->projectNumber;
    }

    public function setProjectNumber(?string $projectNumber): self
    {
        $this->projectNumber = $projectNumber;

        return $this;
    }

    public function getProductArticleCode(): ?string
    {
        return $this->productArticleCode;
    }

    public function setProductArticleCode(?string $productArticleCode): self
    {
        $this->productArticleCode = $productArticleCode;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getDropLocation(): ?Emplacement
    {
        return $this->dropLocation;
    }

    public function setDropLocation(?Emplacement $dropLocation): self
    {
        $this->dropLocation = $dropLocation;

        return $this;
    }

    public function getLineNumber(): ?int
    {
        return $this->lineNumber;
    }

    public function setLineNumber(int $lineNumber): self
    {
        $this->lineNumber = $lineNumber;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getStatusHistory(string $order = Criteria::ASC): Collection {
        return $this->statusHistory
            ->matching(Criteria::create()
                ->orderBy([
                    'date' => $order,
                    'id' => $order,
                ])
            );
    }

    public function addStatusHistory(StatusHistory $statusHistory): self
    {
        if (!$this->statusHistory->contains($statusHistory)) {
            $this->statusHistory[] = $statusHistory;
            $statusHistory->setProductionRequest($this);
        }

        return $this;
    }

    public function removeStatusHistory(StatusHistory $statusHistory): self
    {
        if ($this->statusHistory->removeElement($statusHistory)) {
            if ($statusHistory->getProductionRequest() === $this) {
                $statusHistory->setProductionRequest(null);
            }
        }

        return $this;
    }
}
