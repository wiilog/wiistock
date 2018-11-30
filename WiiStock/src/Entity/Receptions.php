<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ReceptionsRepository")
 */
class Receptions
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
    private $statut;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $date_au_plus_tot;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $date_au_plus_tard;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $date_prevue;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Fournisseurs", inversedBy="receptions")
     */
    private $fournisseur;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $date_reception;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Contenu", mappedBy="reception")
     */
    private $transfert;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Historiques", mappedBy="reception")
     */
    private $historiques;

    public function __construct()
    {
        $this->transfert = new ArrayCollection();
        $this->historiques = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): self
    {
        $this->statut = $statut;

        return $this;
    }

    public function getDateAuPlusTot(): ?\DateTimeInterface
    {
        return $this->date_au_plus_tot;
    }

    public function setDateAuPlusTot(?\DateTimeInterface $date_au_plus_tot): self
    {
        $this->date_au_plus_tot = $date_au_plus_tot;

        return $this;
    }

    public function getDateAuPlusTard(): ?\DateTimeInterface
    {
        return $this->date_au_plus_tard;
    }

    public function setDateAuPlusTard(?\DateTimeInterface $date_au_plus_tard): self
    {
        $this->date_au_plus_tard = $date_au_plus_tard;

        return $this;
    }

    public function getDatePrevue(): ?\DateTimeInterface
    {
        return $this->date_prevue;
    }

    public function setDatePrevue(?\DateTimeInterface $date_prevue): self
    {
        $this->date_prevue = $date_prevue;

        return $this;
    }

    public function getFournisseurs(): ?Fournisseurs
    {
        return $this->fournisseur;
    }

    public function setFournisseurs(?Fournisseurs $fournisseur): self
    {
        $this->fournisseur = $fournisseur;

        return $this;
    }

    public function getDateReception(): ?\DateTimeInterface
    {
        return $this->date_reception;
    }

    public function setDateReception(?\DateTimeInterface $date_reception): self
    {
        $this->date_reception = $date_reception;

        return $this;
    }

    /**
     * @return Collection|Contenu[]
     */
    public function getTransfert(): Collection
    {
        return $this->transfert;
    }

    public function addTransfert(Contenu $transfert): self
    {
        if (!$this->transfert->contains($transfert)) {
            $this->transfert[] = $transfert;
            $transfert->setReception($this);
        }

        return $this;
    }

    public function removeTransfert(Contenu $transfert): self
    {
        if ($this->transfert->contains($transfert)) {
            $this->transfert->removeElement($transfert);
            // set the owning side to null (unless already changed)
            if ($transfert->getReception() === $this) {
                $transfert->setReception(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Historiques[]
     */
    public function getHistoriques(): Collection
    {
        return $this->historiques;
    }

    public function addHistoriques(Historiques $historique): self
    {
        if (!$this->historiques->contains($historique)) {
            $this->historiques[] = $historique;
            $historique->setReception($this);
        }

        return $this;
    }

    public function removeHistoriques(Historiques $historique): self
    {
        if ($this->historiques->contains($historique)) {
            $this->historiques->removeElement($historique);
            // set the owning side to null (unless already changed)
            if ($historique->getReception() === $this) {
                $historique->setReception(null);
            }
        }

        return $this;
    }
}
