<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\OrdresRepository")
 */
class Ordres
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $statut;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $type;

    /**
     * @ORM\Column(type="datetime")
     */
    private $date_ordre;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateurs")
     * @ORM\JoinColumn(nullable=false)
     */
    private $auteur;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Receptions", mappedBy="ordres")
     */
    private $receptions;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Transferts", mappedBy="ordres")
     */
    private $transferts;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Preparations", mappedBy="ordres")
     */
    private $preparations;

    public function __construct()
    {
        $this->receptions = new ArrayCollection();
        $this->transferts = new ArrayCollection();
        $this->preparations = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;

        return $this;
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

    public function getDateOrdre(): ?\DateTimeInterface
    {
        return $this->date_ordre;
    }

    public function setDateOrdre(\DateTimeInterface $date_ordre): self
    {
        $this->date_ordre = $date_ordre;

        return $this;
    }

    public function getAuteur(): ?Utilisateurs
    {
        return $this->auteur;
    }

    public function setAuteur(?Utilisateurs $auteur): self
    {
        $this->auteur = $auteur;

        return $this;
    }

    /**
     * @return Collection|Receptions[]
     */
    public function getReceptions(): Collection
    {
        return $this->receptions;
    }

    public function addReception(Receptions $reception): self
    {
        if (!$this->receptions->contains($reception)) {
            $this->receptions[] = $reception;
            $reception->setOrdres($this);
        }

        return $this;
    }

    public function removeReception(Receptions $reception): self
    {
        if ($this->receptions->contains($reception)) {
            $this->receptions->removeElement($reception);
            // set the owning side to null (unless already changed)
            if ($reception->getOrdres() === $this) {
                $reception->setOrdres(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Transferts[]
     */
    public function getTransferts(): Collection
    {
        return $this->transferts;
    }

    public function addTransfert(Transferts $transfert): self
    {
        if (!$this->transferts->contains($transfert)) {
            $this->transferts[] = $transfert;
            $transfert->setOrdres($this);
        }

        return $this;
    }

    public function removeTransfert(Transferts $transfert): self
    {
        if ($this->transferts->contains($transfert)) {
            $this->transferts->removeElement($transfert);
            // set the owning side to null (unless already changed)
            if ($transfert->getOrdres() === $this) {
                $transfert->setOrdres(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Preparations[]
     */
    public function getPreparations(): Collection
    {
        return $this->preparations;
    }

    public function addPreparation(Preparations $preparation): self
    {
        if (!$this->preparations->contains($preparation)) {
            $this->preparations[] = $preparation;
            $preparation->setOrdres($this);
        }

        return $this;
    }

    public function removePreparation(Preparations $preparation): self
    {
        if ($this->preparations->contains($preparation)) {
            $this->preparations->removeElement($preparation);
            // set the owning side to null (unless already changed)
            if ($preparation->getOrdres() === $this) {
                $preparation->setOrdres(null);
            }
        }

        return $this;
    }
}
