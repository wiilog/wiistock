<?php

namespace App\Entity;

use App\Entity\Traits\AttachmentTrait;
use App\Repository\ReserveRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReserveRepository::class)]
class Reserve
{
    use AttachmentTrait;

    const MINUS = 'moins';
    const PLUS = 'plus';

    const QUANTITY_TYPES = [
        self::MINUS,
        self::PLUS,
    ];

    const TYPE_QUANTITY = 'quantity';
    const TYPE_GENERAL = 'general';
    const TYPE_QUALITY = 'quality';

    const TYPES = [
        self::TYPE_QUANTITY,
        self::TYPE_GENERAL,
        self::TYPE_QUALITY,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $type;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\OneToOne(inversedBy: 'reserve')]
    private ?TruckArrivalLine $line = null;

    #[ORM\Column(nullable: true)]
    private ?int $quantity = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $quantityType = null;

    #[ORM\ManyToOne(inversedBy: 'reserves')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TruckArrival $truckArrival = null;

    public function __construct()
    {
        $this->attachments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getLine(): ?TruckArrivalLine
    {
        return $this->line;
    }

    public function setLine(?TruckArrivalLine $line): self
    {
        if($this->line && $this->line->getReserve() !== $this) {
            $oldExample = $this->line;
            $this->line = null;
            $oldExample->setReserve(null);
        }
        $this->line = $line;
        if($this->line && $this->line->getReserve() !== $this) {
            $this->line->setReserve($this);
        }

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

    public function getQuantityType(): ?string
    {
        return $this->quantityType;
    }

    public function setQuantityType(?string $quantityType): self
    {
        $this->quantityType = $quantityType;

        return $this;
    }

    public function getTruckArrival(): ?TruckArrival
    {
        return $this->truckArrival;
    }

    public function setTruckArrival(?TruckArrival $truckArrival): self
    {
        if($this->truckArrival && $this->truckArrival !== $truckArrival) {
            $this->truckArrival->removeReserve($this);
        }
        $this->truckArrival = $truckArrival;
        $truckArrival?->addReserve($this);

        return $this;
    }
}
