<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\LitigeRepository")
 */
class Litige
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Colis", inversedBy="litige")
     * @ORM\JoinColumn(name="colis_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $colis;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Type", inversedBy="litiges")
     */
    private $type;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\PieceJointe", mappedBy="litige")
     */
    private $piecesJointes;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Statut", inversedBy="litige")
     */
    private $statut;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\LitigeHistory", mappedBy="litige")
     */
    private $litigeHistories;

    public function __construct()
    {
        $this->colis = new ArrayCollection();
        $this->piecesJointes = new ArrayCollection();
        $this->litigeHistories = new ArrayCollection();
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getColis(): ?Colis
    {
        return $this->colis;
    }

    public function setColis(?Colis $colis): self
    {
        $this->colis = $colis;

        return $this;
    }

    public function getType(): ?Type
    {
        return $this->type;
    }

    public function setType(?Type $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function addColi(Colis $coli): self
    {
        if (!$this->colis->contains($coli)) {
            $this->colis[] = $coli;
        }

        return $this;
    }

    public function removeColi(Colis $coli): self
    {
        if ($this->colis->contains($coli)) {
            $this->colis->removeElement($coli);
        }

        return $this;
    }

    /**
     * @return Collection|PieceJointe[]
     */
    public function getPiecesJointes(): Collection
    {
        return $this->piecesJointes;
    }

    public function addPiecesJointe(PieceJointe $piecesJointe): self
    {
        if (!$this->piecesJointes->contains($piecesJointe)) {
            $this->piecesJointes[] = $piecesJointe;
            $piecesJointe->setLitige($this);
        }

        return $this;
    }

    public function removePiecesJointe(PieceJointe $piecesJointe): self
    {
        if ($this->piecesJointes->contains($piecesJointe)) {
            $this->piecesJointes->removeElement($piecesJointe);
            // set the owning side to null (unless already changed)
            if ($piecesJointe->getLitige() === $this) {
                $piecesJointe->setLitige(null);
            }
        }

        return $this;
    }

    public function getStatut(): ?Statut
    {
        return $this->statut;
    }

    public function setStatut(?Statut $statut): self
    {
        $this->statut = $statut;

        return $this;
    }

    /**
     * @return Collection|LitigeHistory[]
     */
    public function getLitigeHistories(): Collection
    {
        return $this->litigeHistories;
    }

    public function addLitigeHistory(LitigeHistory $litigeHistory): self
    {
        if (!$this->litigeHistories->contains($litigeHistory)) {
            $this->litigeHistories[] = $litigeHistory;
            $litigeHistory->setLitige($this);
        }

        return $this;
    }

    public function removeLitigeHistory(LitigeHistory $litigeHistory): self
    {
        if ($this->litigeHistories->contains($litigeHistory)) {
            $this->litigeHistories->removeElement($litigeHistory);
            // set the owning side to null (unless already changed)
            if ($litigeHistory->getLitige() === $this) {
                $litigeHistory->setLitige(null);
            }
        }

        return $this;
    }

}
