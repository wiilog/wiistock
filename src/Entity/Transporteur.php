<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TransporteurRepository")
 */
class Transporteur
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=128, nullable=true)
     */
    private $label;

    /**
     * @ORM\Column(type="string", length=64)
     */
    private $code;

     /**
      *@ORM\OneToMany(targetEntity="App\Entity\Chauffeur", mappedBy="transporteur")
      */
    private $chauffeurs;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Arrivage", mappedBy="transporteur")
     */
    private $arrivages;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Reception", mappedBy="transporteur")
     */
    private $reception;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Dispatch", mappedBy="transporter")
     */
    private $dispatches;

    public function __construct()
    {
        $this->chauffeurs = new ArrayCollection();
        $this->arrivages = new ArrayCollection();
        $this->reception = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

     /**
     * @return Collection|Chauffeur[]
     */
    public function getChauffeurs(): Collection
    {
        return $this->chauffeurs;
    }

    public function addChauffeur(Chauffeur $chauffeur): self
    {
        if (!$this->chauffeurs->contains($chauffeur)) {
            $this->chauffeurs[] = $chauffeur;
            $chauffeur->setTransporteur($this);
        }

        return $this;
    }

    public function removeChauffeur(Chauffeur $chauffeur): self
    {
        if ($this->chauffeurs->contains($chauffeur)) {
            $this->chauffeurs->removeElement($chauffeur);
            // set the owning side to null (unless already changed)
            if ($chauffeur->getTransporteur() === $this) {
                $chauffeur->setTransporteur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Arrivage[]
     */
    public function getArrivages(): Collection
    {
        return $this->arrivages;
    }

    public function addArrivage(Arrivage $arrivage): self
    {
        if (!$this->arrivages->contains($arrivage)) {
            $this->arrivages[] = $arrivage;
            $arrivage->setTransporteur($this);
        }

        return $this;
    }

    public function removeArrivage(Arrivage $arrivage): self
    {
        if ($this->arrivages->contains($arrivage)) {
            $this->arrivages->removeElement($arrivage);
            // set the owning side to null (unless already changed)
            if ($arrivage->getTransporteur() === $this) {
                $arrivage->setTransporteur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Reception[]
     */
    public function getReception(): Collection
    {
        return $this->reception;
    }

    public function addReception(Reception $reception): self
    {
        if (!$this->reception->contains($reception)) {
            $this->reception[] = $reception;
            $reception->setTransporteur($this);
        }

        return $this;
    }

    public function removeReception(Reception $reception): self
    {
        if ($this->reception->contains($reception)) {
            $this->reception->removeElement($reception);
            // set the owning side to null (unless already changed)
            if ($reception->getTransporteur() === $this) {
                $reception->setTransporteur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Dispatch[]
     */
    public function getDispatches(): Collection
    {
        return $this->dispatches;
    }

    public function addDispatch(Dispatch $dispatch): self
    {
        if (!$this->dispatches->contains($dispatch)) {
            $this->dispatches[] = $dispatch;
            $dispatch->setTransporter($this);
        }

        return $this;
    }

    public function removeDispatch(Dispatch $dispatch): self
    {
        if ($this->dispatches->contains($dispatch)) {
            $this->dispatches->removeElement($dispatch);
            // set the owning side to null (unless already changed)
            if ($dispatch->getTransporter() === $this) {
                $dispatch->setTransporter(null);
            }
        }

        return $this;
    }
}
