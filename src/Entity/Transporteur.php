<?php

namespace App\Entity;

use App\Repository\TransporteurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransporteurRepository::class)]
class Transporteur {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 128, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(type: 'string', length: 64)]
    private ?string $code = null;

    #[ORM\OneToMany(targetEntity: Chauffeur::class, mappedBy: 'transporteur')]
    private Collection $chauffeurs;

    #[ORM\OneToMany(targetEntity: Arrivage::class, mappedBy: 'transporteur')]
    private Collection $arrivages;

    #[ORM\OneToMany(targetEntity: Reception::class, mappedBy: 'transporteur')]
    private Collection $reception;

    #[ORM\OneToMany(targetEntity: Dispatch::class, mappedBy: 'carrier')]
    private Collection $dispatches;

    #[ORM\OneToMany(targetEntity: Urgence::class, mappedBy: 'carrier')]
    private Collection $emergencies;

    #[ORM\Column(nullable: true)]
    private ?bool $recurrent = false;

    #[ORM\Column(nullable: true)]
    private ?int $minTrackingNumberLength = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxTrackingNumberLength = null;

    public function __construct() {
        $this->chauffeurs = new ArrayCollection();
        $this->arrivages = new ArrayCollection();
        $this->reception = new ArrayCollection();
        $this->emergencies = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getLabel(): ?string {
        return $this->label;
    }

    public function setLabel(?string $label): self {
        $this->label = $label;

        return $this;
    }

    public function getCode(): ?string {
        return $this->code;
    }

    public function setCode(string $code): self {
        $this->code = $code;

        return $this;
    }

    /**
     * @return Collection|Chauffeur[]
     */
    public function getChauffeurs(): Collection {
        return $this->chauffeurs;
    }

    public function addChauffeur(Chauffeur $chauffeur): self {
        if(!$this->chauffeurs->contains($chauffeur)) {
            $this->chauffeurs[] = $chauffeur;
            $chauffeur->setTransporteur($this);
        }

        return $this;
    }

    public function removeChauffeur(Chauffeur $chauffeur): self {
        if($this->chauffeurs->contains($chauffeur)) {
            $this->chauffeurs->removeElement($chauffeur);
            // set the owning side to null (unless already changed)
            if($chauffeur->getTransporteur() === $this) {
                $chauffeur->setTransporteur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Arrivage[]
     */
    public function getArrivages(): Collection {
        return $this->arrivages;
    }

    public function addArrivage(Arrivage $arrivage): self {
        if(!$this->arrivages->contains($arrivage)) {
            $this->arrivages[] = $arrivage;
            $arrivage->setTransporteur($this);
        }

        return $this;
    }

    public function removeArrivage(Arrivage $arrivage): self {
        if($this->arrivages->contains($arrivage)) {
            $this->arrivages->removeElement($arrivage);
            // set the owning side to null (unless already changed)
            if($arrivage->getTransporteur() === $this) {
                $arrivage->setTransporteur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Reception[]
     */
    public function getReception(): Collection {
        return $this->reception;
    }

    public function addReception(Reception $reception): self {
        if(!$this->reception->contains($reception)) {
            $this->reception[] = $reception;
            $reception->setTransporteur($this);
        }

        return $this;
    }

    public function removeReception(Reception $reception): self {
        if($this->reception->contains($reception)) {
            $this->reception->removeElement($reception);
            // set the owning side to null (unless already changed)
            if($reception->getTransporteur() === $this) {
                $reception->setTransporteur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Dispatch[]
     */
    public function getDispatches(): Collection {
        return $this->dispatches;
    }

    public function addDispatch(Dispatch $dispatch): self {
        if(!$this->dispatches->contains($dispatch)) {
            $this->dispatches[] = $dispatch;
            $dispatch->setCarrier($this);
        }

        return $this;
    }

    public function removeDispatch(Dispatch $dispatch): self {
        if($this->dispatches->contains($dispatch)) {
            $this->dispatches->removeElement($dispatch);
            // set the owning side to null (unless already changed)
            if($dispatch->getCarrier() === $this) {
                $dispatch->setCarrier(null);
            }
        }

        return $this;
    }

    public function getEmergencies(): Collection {
        return $this->emergencies;
    }

    public function addEmergency(Urgence $urgence): self {
        if(!$this->emergencies->contains($urgence)) {
            $this->emergencies[] = $urgence;
            $urgence->setCarrier($this);
        }

        return $this;
    }

    public function removeEmergency(Urgence $urgence): self {
        if($this->emergencies->removeElement($urgence)) {
            if($urgence->getCarrier() === $this) {
                $urgence->setCarrier(null);
            }
        }

        return $this;
    }

    public function setEmergencies(?array $emergencies): self {
        foreach($this->getEmergencies()->toArray() as $emergency) {
            $this->removeEmergency($emergency);
        }

        $this->emergencies = new ArrayCollection();
        foreach($emergencies as $emergency) {
            $this->addEmergency($emergency);
        }

        return $this;
    }

    public function isRecurrent(): ?bool
    {
        return $this->recurrent;
    }

    public function setRecurrent(bool $recurrent): self
    {
        $this->recurrent = $recurrent;

        return $this;
    }

    public function getMinTrackingNumberLength(): ?int
    {
        return $this->minTrackingNumberLength;
    }

    public function setMinTrackingNumberLength(?int $minTrackingNumberLength): self
    {
        $this->minTrackingNumberLength = $minTrackingNumberLength;

        return $this;
    }

    public function getMaxTrackingNumberLength(): ?int
    {
        return $this->maxTrackingNumberLength;
    }

    public function setMaxTrackingNumberLength(?int $maxTrackingNumberLength): self
    {
        $this->maxTrackingNumberLength = $maxTrackingNumberLength;

        return $this;
    }

}
