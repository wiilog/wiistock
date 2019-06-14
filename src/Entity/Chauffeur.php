<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ChauffeurRepository")
 */
class Chauffeur
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=64)
     */
    private $nom;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    private $prenom;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    private $documentID;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Transporteur", inversedBy="chauffeurs")
     * @ORM\JoinColumn(name="transporteur_id", referencedColumnName="id", onDelete="SET NULL", nullable=true)
     */
    private $transporteur;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Arrivage", mappedBy="chauffeur")
     */
    private $arrivages;

    public function __construct()
    {
        $this->arrivages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(?string $prenom): self
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getDocumentID(): ?string
    {
        return $this->documentID;
    }

    public function setDocumentID(?string $documentID): self
    {
        $this->documentID = $documentID;

        return $this;
    }

    public function getTransporteur(): ?Transporteur
    {
        return $this->transporteur;
    }

    public function setTransporteur(?Transporteur $transporteur): self
    {
        $this->transporteur = $transporteur;

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
            $arrivage->setChauffeur($this);
        }

        return $this;
    }

    public function removeArrivage(Arrivage $arrivage): self
    {
        if ($this->arrivages->contains($arrivage)) {
            $this->arrivages->removeElement($arrivage);
            // set the owning side to null (unless already changed)
            if ($arrivage->getChauffeur() === $this) {
                $arrivage->setChauffeur(null);
            }
        }

        return $this;
    }
}
