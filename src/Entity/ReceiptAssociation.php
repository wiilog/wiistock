<?php

namespace App\Entity;

use App\Entity\Tracking\Pack;
use App\Repository\ReceiptAssociationRepository;
use App\Service\FormatService;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use WiiCommon\Helper\Stream;

#[ORM\Entity(repositoryClass: ReceiptAssociationRepository::class)]
class ReceiptAssociation {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $creationDate = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $user = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $receptionNumber = null;

    #[ORM\ManyToMany(targetEntity: Pack::class, inversedBy: 'receiptAssociations')]
    #[ORM\JoinTable(name: 'receipt_association_logistic_unit')]
    #[ORM\InverseJoinColumn(name: "logistic_unit_id", referencedColumnName: "id")]
    private Collection $logisticUnits;

    public function __construct() {
        $this->logisticUnits = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getCreationDate(): ?DateTime {
        return $this->creationDate;
    }

    public function setCreationDate(?DateTime $creationDate): self {
        $this->creationDate = $creationDate;

        return $this;
    }

    public function getUser(): ?Utilisateur {
        return $this->user;
    }

    public function setUser(?Utilisateur $user): self {
        $this->user = $user;

        return $this;
    }

    public function serialize(FormatService $formatService, Utilisateur $user = null): array {
        return [
            'creationDate' => $formatService->datetime($this->getCreationDate(), "", false, $user),
            'packCode' => Stream::From($this->getLogisticUnits())->map(static fn(Pack $logisticUnits) => $logisticUnits->getCode())->join(', ') ?? '',
            'receptionNumber' => $this->getReceptionNumber() ?? '',
            'user' => $formatService->user($this->getUser()),
        ];
    }

    public function getReceptionNumber(): ?string {
        return $this->receptionNumber;
    }

    public function setReceptionNumber(?string $receptionNumber): self {
        $this->receptionNumber = $receptionNumber;

        return $this;
    }

    public function getLogisticUnits(): Collection
    {
        return $this->logisticUnits;
    }

    public function setLogisticUnits(array $logisticUnits): self {
        $this->logisticUnits = new ArrayCollection($logisticUnits);

        return $this;
    }

    public function addPack(Pack $pack): self {
        if (!$this->logisticUnits->contains($pack)) {
            $this->logisticUnits[] = $pack;
        }

        return $this;
    }

    public function removePack(Pack $pack): self {
        if ($this->logisticUnits->removeElement($pack)) {
            $pack->removeReceiptAssociation($this);
        }

        return $this;
    }
}
