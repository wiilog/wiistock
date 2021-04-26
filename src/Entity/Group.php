<?php

namespace App\Entity;

use App\Repository\GroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=GroupRepository::class)
 * @ORM\Table(name="`group`")
 */
class Group
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string")
     */
    private $code;

    /**
     * @ORM\ManyToOne(targetEntity=Nature::class, inversedBy="groups")
     */
    private $nature;

    /**
     * @ORM\Column(type="integer")
     */
    private $iteration;

    /**
     * @ORM\Column(type="integer")
     */
    private $weight;

    /**
     * @ORM\Column(type="integer")
     */
    private $volume;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $comment;

    /**
     * @ORM\OneToOne(targetEntity=TrackingMovement::class, cascade={"persist", "remove"}, inversedBy="linkedGroupLastTracking")
     */
    private $lastTracking;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Pack", mappedBy="packGroup")
     */
    private $packs;

    /**
     * @var Collection
     * @ORM\OneToMany(targetEntity=TrackingMovement::class, mappedBy="packGroup")
     * @ORM\JoinColumn(onDelete="CASCADE")
     * @ORM\OrderBy({"datetime" = "DESC", "id" = "DESC"})
     */
    private $trackingMovements;

    public function __construct()
    {
        $this->packs = new ArrayCollection();
        $this->trackingMovements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getNature(): ?Nature
    {
        return $this->nature;
    }

    public function setNature(?Nature $nature): self
    {
        $this->nature = $nature;

        return $this;
    }

    public function getIteration(): ?int
    {
        return $this->iteration;
    }

    public function setIteration(int $iteration): self
    {
        $this->iteration = $iteration;

        return $this;
    }

    public function getWeight(): ?int
    {
        return $this->weight;
    }

    public function setWeight(int $weight): self
    {
        $this->weight = $weight;

        return $this;
    }

    public function getVolume(): ?int
    {
        return $this->volume;
    }

    public function setVolume(int $volume): self
    {
        $this->volume = $volume;

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

    /**
     * @return Collection|Pack[]
     */
    public function getPacks(): Collection
    {
        return $this->packs;
    }

    public function addPack(Pack $pack): self
    {
        if (!$this->packs->contains($pack)) {
            $this->packs[] = $pack;
            $pack->setGroup($this);
        }

        return $this;
    }

    public function removePack(Pack $pack): self
    {
        if ($this->packs->contains($pack)) {
            $this->packs->removeElement($pack);
            // set the owning side to null (unless already changed)
            if ($pack->getGroup() === $this) {
                $pack->setGroup(null);
            }
        }

        return $this;
    }

    public function getLastTracking(): ?TrackingMovement
    {
        return $this->lastTracking;
    }

    public function setLastTracking(?TrackingMovement $lastTracking): self
    {
        $this->lastTracking = $lastTracking;

        return $this;
    }

    /**
     * @return Collection|TrackingMovement[]
     */
    public function getTrackingMovements(): Collection
    {
        return $this->trackingMovements;
    }

    public function addTrackingMovement(TrackingMovement $trackingMovement): self
    {
        if (!$this->trackingMovements->contains($trackingMovement)) {
            $this->trackingMovements[] = $trackingMovement;
            $trackingMovement->setPackGroup($this);
        }

        return $this;
    }

    public function removeTrackingMovement(TrackingMovement $trackingMovement): self
    {
        if ($this->trackingMovements->removeElement($trackingMovement)) {
            // set the owning side to null (unless already changed)
            if ($trackingMovement->getPackGroup() === $this) {
                $trackingMovement->setPackGroup(null);
            }
        }

        return $this;
    }
}
