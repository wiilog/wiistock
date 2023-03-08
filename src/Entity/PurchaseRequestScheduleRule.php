<?php

namespace App\Entity;

use App\Repository\PurchaseRequestScheduleRuleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PurchaseRequestScheduleRuleRepository::class)]
class PurchaseRequestScheduleRule extends ScheduleRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToMany(targetEntity: Zone::class)]
    private Collection $zones;

    #[ORM\ManyToMany(targetEntity: Fournisseur::class)]
    private Collection $suppliers;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $requester = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    private ?string $emailSubject = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Statut $status = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->zones = new ArrayCollection();
        $this->suppliers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, Zone>
     */
    public function getZones(): Collection
    {
        return $this->zones;
    }

    public function addZone(Zone $zone): self
    {
        if (!$this->zones->contains($zone)) {
            $this->zones->add($zone);
        }

        return $this;
    }

    public function removeZone(Zone $zone): self
    {
        $this->zones->removeElement($zone);

        return $this;
    }

    public function setZones(Collection $zones): self
    {
        $this->zones = $zones;

        return $this;
    }

    /**
     * @return Collection<int, Fournisseur>
     */
    public function getSuppliers(): Collection
    {
        return $this->suppliers;
    }

    public function addSupplier(Fournisseur $supplier): self
    {
        if (!$this->suppliers->contains($supplier)) {
            $this->suppliers->add($supplier);
        }

        return $this;
    }

    public function removeSupplier(Fournisseur $supplier): self
    {
        $this->suppliers->removeElement($supplier);

        return $this;
    }

    public function setSuppliers(Collection $suppliers): self
    {
        $this->suppliers = $suppliers;

        return $this;
    }

    public function getRequester(): ?Utilisateur
    {
        return $this->requester;
    }

    public function setRequester(?Utilisateur $requester): self
    {
        $this->requester = $requester;

        return $this;
    }

    public function getEmailSubject(): ?string
    {
        return $this->emailSubject;
    }

    public function setEmailSubject(string $emailSubject): self
    {
        $this->emailSubject = $emailSubject;

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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
