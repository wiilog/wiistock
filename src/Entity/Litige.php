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
	// statuts de la catégorie litige
	const CONFORME = 'conforme';
	const ATTENTE_ACHETEUR = 'attente retour acheteur';
	const TRAITE_ACHETEUR = 'traité par acheteur';
	const SOLDE = 'soldé';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;


	/**
	 * @ORM\Column(type="date", nullable=true)
	 */
	private $creationDate;

	/**
	 * @ORM\Column(type="date", nullable=true)
	 */
	private $updateDate;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Colis", inversedBy="litiges")
     */
    private $colis;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Type", inversedBy="litiges")
     */
    private $type;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\PieceJointe", mappedBy="litige")
     */
    private $attachements;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Statut", inversedBy="litiges")
     */
    private $status;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\LitigeHistoric", mappedBy="litige")
     */
    private $litigeHistorics;

    public function __construct()
    {
        $this->attachements = new ArrayCollection();
        $this->litigeHistorics = new ArrayCollection();
        $this->colis = new ArrayCollection();
    }


    public function getId(): ?int
    {
        return $this->id;
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

    /**
     * @return Collection|PieceJointe[]
     */
    public function getAttachements(): Collection
    {
        return $this->attachements;
    }

    public function addPiecesJointe(PieceJointe $piecesJointe): self
    {
        if (!$this->attachements->contains($piecesJointe)) {
            $this->attachements[] = $piecesJointe;
            $piecesJointe->setLitige($this);
        }

        return $this;
    }

    public function removePiecesJointe(PieceJointe $piecesJointe): self
    {
        if ($this->attachements->contains($piecesJointe)) {
            $this->attachements->removeElement($piecesJointe);
            // set the owning side to null (unless already changed)
            if ($piecesJointe->getLitige() === $this) {
                $piecesJointe->setLitige(null);
            }
        }

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

    /**
     * @return Collection|LitigeHistoric[]
     */
    public function getLitigeHistorics(): Collection
    {
        return $this->litigeHistorics;
    }

    public function addLitigeHistory(LitigeHistoric $litigeHistory): self
    {
        if (!$this->litigeHistorics->contains($litigeHistory)) {
            $this->litigeHistorics[] = $litigeHistory;
            $litigeHistory->setLitige($this);
        }

        return $this;
    }

    public function removeLitigeHistory(LitigeHistoric $litigeHistory): self
    {
        if ($this->litigeHistorics->contains($litigeHistory)) {
            $this->litigeHistorics->removeElement($litigeHistory);
            // set the owning side to null (unless already changed)
            if ($litigeHistory->getLitige() === $this) {
                $litigeHistory->setLitige(null);
            }
        }

        return $this;
    }

    public function getCreationDate(): ?\DateTimeInterface
    {
        return $this->creationDate;
    }

    public function setCreationDate(\DateTimeInterface $creationDate): self
    {
        $this->creationDate = $creationDate;

        return $this;
    }

    public function getUpdateDate(): ?\DateTimeInterface
    {
        return $this->updateDate;
    }

    public function setUpdateDate(\DateTimeInterface $updateDate): self
    {
        $this->updateDate = $updateDate;

        return $this;
    }

    public function addAttachement(PieceJointe $attachement): self
    {
        if (!$this->attachements->contains($attachement)) {
            $this->attachements[] = $attachement;
            $attachement->setLitige($this);
        }

        return $this;
    }

    public function removeAttachement(PieceJointe $attachement): self
    {
        if ($this->attachements->contains($attachement)) {
            $this->attachements->removeElement($attachement);
            // set the owning side to null (unless already changed)
            if ($attachement->getLitige() === $this) {
                $attachement->setLitige(null);
            }
        }

        return $this;
    }

    public function addLitigeHistoric(LitigeHistoric $litigeHistoric): self
    {
        if (!$this->litigeHistorics->contains($litigeHistoric)) {
            $this->litigeHistorics[] = $litigeHistoric;
            $litigeHistoric->setLitige($this);
        }

        return $this;
    }

    public function removeLitigeHistoric(LitigeHistoric $litigeHistoric): self
    {
        if ($this->litigeHistorics->contains($litigeHistoric)) {
            $this->litigeHistorics->removeElement($litigeHistoric);
            // set the owning side to null (unless already changed)
            if ($litigeHistoric->getLitige() === $this) {
                $litigeHistoric->setLitige(null);
            }
        }

        return $this;
    }

    public function getColis()
    {
        return $this->colis;
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

}
