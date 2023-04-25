<?php

namespace App\Entity\ShippingRequest;

use App\Entity\Interfaces\StatusHistoryContainer;
use App\Entity\ReferenceArticle;
use App\Entity\StatusHistory;
use App\Entity\Statut;
use App\Entity\Transporteur;
use App\Entity\Utilisateur;
use App\Repository\ShippingRequest\ShippingRequestRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use WiiCommon\Helper\Stream;

#[ORM\Entity(repositoryClass: ShippingRequestRepository::class)]
class ShippingRequest extends StatusHistoryContainer {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
    private ?DateTime $createdAt = null;

    #[ORM\Column(type: Types::JSON)]
    private array $requesterPhoneNumbers = [];

    #[ORM\Column(type: Types::STRING)]
    private ?string $customerOrderNumber = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $freeDelivery = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $compliantArticles = false;

    #[ORM\Column(type: Types::STRING)]
    private ?string $customerName = null;

    #[ORM\Column(type: Types::STRING)]
    private ?string $customerPhone = null;

    #[ORM\Column(type: Types::STRING)]
    private ?string $customerRecipient = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $customerAddress = null;

    /**
     * "Date de prise en charge souhaitée"
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTime $requestCaredAt = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $comment = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTime $validatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTime $plannedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTime $treatedAt = null;

    #[ORM\ManyToOne(targetEntity: Statut::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Statut $status = null;

    #[ORM\ManyToOne(targetEntity: Transporteur::class)]
    private ?Transporteur $carrier = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $createdBy = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $validatedBy = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $plannedBy = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    private ?Utilisateur $treatedBy = null;

    #[ORM\ManyToMany(targetEntity: Utilisateur::class)]
    private Collection $requesters;

    #[ORM\OneToMany(mappedBy: 'request', targetEntity: ShippingRequestExpectedLine::class)]
    private Collection $expectedLines;

    #[ORM\OneToMany(mappedBy: 'request', targetEntity: ShippingRequestLine::class)]
    private Collection $lines;

    #[ORM\OneToMany(mappedBy: 'shippingRequest', targetEntity: StatusHistory::class)]
    private Collection $statusHistory;

    public function __construct() {
        $this->requesters = new ArrayCollection();
        $this->expectedLines = new ArrayCollection();
        $this->statusHistory = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getCreatedAt(): ?DateTime {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTime $createdAt): self {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getRequesterPhoneNumbers(): array {
        return $this->requesterPhoneNumbers;
    }

    public function setRequesterPhoneNumbers(array $requesterPhoneNumbers): self {
        $this->requesterPhoneNumbers = $requesterPhoneNumbers;
        return $this;
    }

    public function getCustomerOrderNumber(): ?string {
        return $this->customerOrderNumber;
    }

    public function setCustomerOrderNumber(?string $customerOrderNumber): self {
        $this->customerOrderNumber = $customerOrderNumber;
        return $this;
    }

    public function isFreeDelivery(): bool {
        return $this->freeDelivery;
    }

    public function setFreeDelivery(bool $freeDelivery): self {
        $this->freeDelivery = $freeDelivery;
        return $this;
    }

    public function isCompliantArticles(): bool {
        return $this->compliantArticles;
    }

    public function setCompliantArticles(bool $compliantArticles): self {
        $this->compliantArticles = $compliantArticles;
        return $this;
    }

    public function getCustomerPhone(): ?string {
        return $this->customerPhone;
    }

    public function setCustomerPhone(?string $customerPhone): self {
        $this->customerPhone = $customerPhone;
        return $this;
    }

    public function getCustomerName(): ?string {
        return $this->customerName;
    }

    public function setCustomerName(?string $customerName): self {
        $this->customerName = $customerName;
        return $this;
    }

    public function getCustomerRecipient(): ?string {
        return $this->customerRecipient;
    }

    public function setCustomerRecipient(?string $customerRecipient): self {
        $this->customerRecipient = $customerRecipient;
        return $this;
    }

    public function getCustomerAddress(): ?string {
        return $this->customerAddress;
    }

    public function setCustomerAddress(?string $customerAddress): self {
        $this->customerAddress = $customerAddress;
        return $this;
    }

    public function getCreatedBy(): ?Utilisateur {
        return $this->createdBy;
    }

    public function setCreatedBy(?Utilisateur $createdBy): self {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getRequesters(): Collection {
        return $this->requesters;
    }

    public function setRequesters(Collection $requesters): self {
        $this->requesters = $requesters;
        return $this;
    }

    public function getRequestCaredAt(): ?DateTime {
        return $this->requestCaredAt;
    }

    public function setRequestCaredAt(?DateTime $requestCaredAt): self {
        $this->requestCaredAt = $requestCaredAt;
        return $this;
    }

    public function getCarrier(): ?Transporteur {
        return $this->carrier;
    }

    public function setCarrier(?Transporteur $carrier): self {
        $this->carrier = $carrier;
        return $this;
    }

    public function getComment(): ?string {
        return $this->comment;
    }

    public function setComment(?string $comment): self {
        $this->comment = $comment;
        return $this;
    }

    public function getExpectedLines(): Collection {
        return $this->expectedLines;
    }

    public function getExpectedLine(ReferenceArticle $referenceArticle): ?ShippingRequestExpectedLine {
        return Stream::from($this->expectedLines)
            ->find(fn(ShippingRequestExpectedLine $line) => $line->getReferenceArticle() === $referenceArticle);
    }

    public function addExpectedLine(ShippingRequestExpectedLine $line): self {
        if (!$this->expectedLines->contains($line)) {
            $this->expectedLines[] = $line;
            $line->setRequest($this);
        }

        return $this;
    }

    public function removeExpectedLine(ShippingRequestExpectedLine $line): self {
        if ($this->expectedLines->removeElement($line)) {
            if ($line->getRequest() === $this) {
                $line->setRequest(null);
            }
        }

        return $this;
    }

    public function getLines(): Collection {
        return $this->lines;
    }

    public function addLine(ShippingRequestLine $line): self {
        if (!$this->lines->contains($line)) {
            $this->lines[] = $line;
            $line->setRequest($this);
        }

        return $this;
    }

    public function removeLine(ShippingRequestLine $line): self {
        if ($this->lines->removeElement($line)) {
            if ($line->getRequest() === $this) {
                $line->setRequest(null);
            }
        }

        return $this;
    }

    public function getValidatedAt(): ?DateTime {
        return $this->validatedAt;
    }

    public function setValidatedAt(?DateTime $validatedAt): self {
        $this->validatedAt = $validatedAt;
        return $this;
    }

    public function getPlannedAt(): ?DateTime {
        return $this->plannedAt;
    }

    public function setPlannedAt(?DateTime $plannedAt): self {
        $this->plannedAt = $plannedAt;
        return $this;
    }

    public function getTreatedAt(): ?DateTime {
        return $this->treatedAt;
    }

    public function setTreatedAt(?DateTime $treatedAt): self {
        $this->treatedAt = $treatedAt;
        return $this;
    }

    public function getValidatedBy(): ?Utilisateur {
        return $this->validatedBy;
    }

    public function setValidatedBy(?Utilisateur $validatedBy): self {
        $this->validatedBy = $validatedBy;
        return $this;
    }

    public function getPlannedBy(): ?Utilisateur {
        return $this->plannedBy;
    }

    public function setPlannedBy(?Utilisateur $plannedBy): self {
        $this->plannedBy = $plannedBy;
        return $this;
    }

    public function getTreatedBy(): ?Utilisateur {
        return $this->treatedBy;
    }

    public function setTreatedBy(?Utilisateur $treatedBy): self {
        $this->treatedBy = $treatedBy;
        return $this;
    }

    public function getStatus(): ?Statut {
        return $this->status;
    }

    public function setStatus(?Statut $status): self {
        $this->status = $status;
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
            $statusHistory->setShippingRequest($this);
        }

        return $this;
    }

    public function removeStatusHistory(StatusHistory $statusHistory): self
    {
        if ($this->statusHistory->removeElement($statusHistory)) {
            // set the owning side to null (unless already changed)
            if ($statusHistory->getShippingRequest() === $this) {
                $statusHistory->setShippingRequest(null);
            }
        }

        return $this;
    }
}
