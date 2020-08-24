<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PackRepository")
 */
class Pack
{

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $code;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Arrivage", inversedBy="packs")
     */
    private $arrivage;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Litige", mappedBy="packs")
     */
    private $litiges;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Nature", inversedBy="packs")
     */
    private $nature;

    /**
     * @var MouvementTraca
     * @ORM\ManyToOne(targetEntity="App\Entity\MouvementTraca", inversedBy="linkedPackLastDrops")
     * @ORM\JoinColumn(name="last_drop_id")
     */
    private $lastDrop;

    /**
     * @var MouvementTraca
     * @ORM\ManyToOne(targetEntity="App\Entity\MouvementTraca", inversedBy="linkedPackLastTrackings")
     * @ORM\JoinColumn(name="last_tracking_id")
     */
    private $lastTracking;

    /**
     * @var Collection
     * @ORM\OneToMany(targetEntity="App\Entity\MouvementTraca", mappedBy="pack", cascade={"remove"})
     * @ORM\OrderBy({"datetime" = "DESC"})
     */
    private $trackingMovements;

    /**
     * @ORM\Column(type="integer", options={"default": 0})
     */
    private $quantity;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\PackAcheminement", mappedBy="pack", orphanRemoval=true)
     */
    private $packAcheminements;

    public function __construct() {
        $this->litiges = new ArrayCollection();
        $this->trackingMovements = new ArrayCollection();
        $this->packAcheminements = new ArrayCollection();
        $this->quantity = 0;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string {
        return $this->code;
    }

    public function setCode(?string $code): self {
        $this->code = $code;
        return $this;
    }

    public function getArrivage(): ?Arrivage
    {
        return $this->arrivage;
    }

    public function setArrivage(?Arrivage $arrivage): self
    {
        $this->arrivage = $arrivage;

        return $this;
    }

    /**
     * @return Collection|Litige[]
     */
    public function getLitiges(): Collection
    {
        return $this->litiges;
    }

    public function addLitige(Litige $litige): self
    {
        if (!$this->litiges->contains($litige)) {
            $this->litiges[] = $litige;
            $litige->addPack($this);
        }

        return $this;
    }

    public function removeLitige(Litige $litige): self
    {
        if ($this->litiges->contains($litige)) {
            $this->litiges->removeElement($litige);
            $litige->removePack($this);
        }

        return $this;
    }

    public function getNature(): ?Nature
    {
        return $this->nature;
    }

    public function setNature(?Nature $nature): self
    {
        $this->nature = $nature;

        return $this;
    }

    public function getLastDrop(): ?MouvementTraca
    {
        return $this->lastDrop;
    }

    public function setLastDrop(?MouvementTraca $lastDrop): self
    {
        $this->lastDrop = $lastDrop;

        return $this;
    }

    public function getLastTracking(): ?MouvementTraca
    {
        return $this->lastTracking;
    }

    public function setLastTracking(?MouvementTraca $lastDrop): self
    {
        $this->lastTracking = $lastDrop;

        return $this;
    }

    /**
     * @return Collection|MouvementTraca[]
     */
    public function getTrackingMovements(): Collection {
        return $this->trackingMovements;
    }

    public function addTrackingMovement(MouvementTraca $trackingMovement): self
    {
        if (!$this->trackingMovements->contains($trackingMovement)) {
            $this->trackingMovements[] = $trackingMovement;
            $trackingMovement->setPack($this);
        }

        return $this;
    }

    public function removeTrackingMovement(MouvementTraca $trackingMovement): self
    {
        if ($this->trackingMovements->contains($trackingMovement)) {
            $this->trackingMovements->removeElement($trackingMovement);
            // set the owning side to null (unless already changed)
            if ($trackingMovement->getPack() === $this) {
                $trackingMovement->setPack(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|PackAcheminement[]
     */
    public function getPackAcheminements(): Collection
    {
        return $this->packAcheminements;
    }

    public function addPackAcheminement(PackAcheminement $packAcheminement): self
    {
        if (!$this->packAcheminements->contains($packAcheminement)) {
            $this->packAcheminements[] = $packAcheminement;
            $packAcheminement->setPack($this);
        }

        return $this;
    }

    public function removePackAcheminement(PackAcheminement $packAcheminement): self
    {
        if ($this->packAcheminements->contains($packAcheminement)) {
            $this->packAcheminements->removeElement($packAcheminement);
            // set the owning side to null (unless already changed)
            if ($packAcheminement->getPack() === $this) {
                $packAcheminement->setPack(null);
            }
        }

        return $this;
    }

    public function setQuantity(int $quantity): self {
        $this->quantity = $quantity;
        return $this;
    }

    public function getQuantity(): int {
        return $this->quantity;
    }
}
