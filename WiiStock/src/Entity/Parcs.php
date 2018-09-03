<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ParcsRepository")
 */
class Parcs
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
    private $modele;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $statut;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $n_Parc;

    /**
     * @ORM\Column(type="date")
     */
    private $mise_en_circulation;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $fournisseur;

    /**
     * @ORM\Column(type="integer")
     */
    private $poids;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $mode_acquisition;

    /**
     * @ORM\Column(type="text")
     */
    private $commentaire;

    /**
     * @ORM\Column(type="date")
     */
    private $incorporation;

    /**
     * @ORM\Column(type="date")
     */
    private $mise_en_service;

    /**
     * @ORM\Column(type="date")
     */
    private $sortie;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $motif;

    /**
     * @ORM\Column(type="text")
     */
    private $commentaire_sortie;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Filiales", mappedBy="parc")
     */
    private $filiales;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\CategoriesVehicules", mappedBy="parc")
     */
    private $categoriesVehicules;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Marques", mappedBy="parc")
     */
    private $marques;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Sites", mappedBy="parc")
     */
    private $sites;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\SousCategoriesVehicules", mappedBy="parc")
     */
    private $sousCategoriesVehicules;

    public function __construct()
    {
        $this->filiales = new ArrayCollection();
        $this->categoriesVehicules = new ArrayCollection();
        $this->marques = new ArrayCollection();
        $this->sites = new ArrayCollection();
        $this->sousCategoriesVehicules = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getModele(): ?string
    {
        return $this->modele;
    }

    public function setModele(string $modele): self
    {
        $this->modele = $modele;

        return $this;
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

    public function getNParc(): ?string
    {
        return $this->n_Parc;
    }

    public function setNParc(string $n_Parc): self
    {
        $this->n_Parc = $n_Parc;

        return $this;
    }

    public function getMiseEnCirculation(): ?\DateTimeInterface
    {
        return $this->mise_en_circulation;
    }

    public function setMiseEnCirculation(\DateTimeInterface $mise_en_circulation): self
    {
        $this->mise_en_circulation = $mise_en_circulation;

        return $this;
    }

    public function getFournisseur(): ?string
    {
        return $this->fournisseur;
    }

    public function setFournisseur(string $fournisseur): self
    {
        $this->fournisseur = $fournisseur;

        return $this;
    }

    public function getPoids(): ?int
    {
        return $this->poids;
    }

    public function setPoids(int $poids): self
    {
        $this->poids = $poids;

        return $this;
    }

    public function getModeAcquisition(): ?string
    {
        return $this->mode_acquisition;
    }

    public function setModeAcquisition(string $mode_acquisition): self
    {
        $this->mode_acquisition = $mode_acquisition;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(string $commentaire): self
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getIncorporation(): ?\DateTimeInterface
    {
        return $this->incorporation;
    }

    public function setIncorporation(\DateTimeInterface $incorporation): self
    {
        $this->incorporation = $incorporation;

        return $this;
    }

    public function getMiseEnService(): ?\DateTimeInterface
    {
        return $this->mise_en_service;
    }

    public function setMiseEnService(\DateTimeInterface $mise_en_service): self
    {
        $this->mise_en_service = $mise_en_service;

        return $this;
    }

    public function getSortie(): ?\DateTimeInterface
    {
        return $this->sortie;
    }

    public function setSortie(\DateTimeInterface $sortie): self
    {
        $this->sortie = $sortie;

        return $this;
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function setMotif(string $motif): self
    {
        $this->motif = $motif;

        return $this;
    }

    public function getCommentaireSortie(): ?string
    {
        return $this->commentaire_sortie;
    }

    public function setCommentaireSortie(string $commentaire_sortie): self
    {
        $this->commentaire_sortie = $commentaire_sortie;

        return $this;
    }

    /**
     * @return Collection|Filiales[]
     */
    public function getFiliales(): Collection
    {
        return $this->filiales;
    }

    public function addFiliale(Filiales $filiale): self
    {
        if (!$this->filiales->contains($filiale)) {
            $this->filiales[] = $filiale;
            $filiale->setParc($this);
        }

        return $this;
    }

    public function removeFiliale(Filiales $filiale): self
    {
        if ($this->filiales->contains($filiale)) {
            $this->filiales->removeElement($filiale);
            // set the owning side to null (unless already changed)
            if ($filiale->getParc() === $this) {
                $filiale->setParc(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|CategoriesVehicules[]
     */
    public function getCategoriesVehicules(): Collection
    {
        return $this->categoriesVehicules;
    }

    public function addCategoriesVehicule(CategoriesVehicules $categoriesVehicule): self
    {
        if (!$this->categoriesVehicules->contains($categoriesVehicule)) {
            $this->categoriesVehicules[] = $categoriesVehicule;
            $categoriesVehicule->setParc($this);
        }

        return $this;
    }

    public function removeCategoriesVehicule(CategoriesVehicules $categoriesVehicule): self
    {
        if ($this->categoriesVehicules->contains($categoriesVehicule)) {
            $this->categoriesVehicules->removeElement($categoriesVehicule);
            // set the owning side to null (unless already changed)
            if ($categoriesVehicule->getParc() === $this) {
                $categoriesVehicule->setParc(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Marques[]
     */
    public function getMarques(): Collection
    {
        return $this->marques;
    }

    public function addMarque(Marques $marque): self
    {
        if (!$this->marques->contains($marque)) {
            $this->marques[] = $marque;
            $marque->setParc($this);
        }

        return $this;
    }

    public function removeMarque(Marques $marque): self
    {
        if ($this->marques->contains($marque)) {
            $this->marques->removeElement($marque);
            // set the owning side to null (unless already changed)
            if ($marque->getParc() === $this) {
                $marque->setParc(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Sites[]
     */
    public function getSites(): Collection
    {
        return $this->sites;
    }

    public function addSite(Sites $site): self
    {
        if (!$this->sites->contains($site)) {
            $this->sites[] = $site;
            $site->setParc($this);
        }

        return $this;
    }

    public function removeSite(Sites $site): self
    {
        if ($this->sites->contains($site)) {
            $this->sites->removeElement($site);
            // set the owning side to null (unless already changed)
            if ($site->getParc() === $this) {
                $site->setParc(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|SousCategoriesVehicules[]
     */
    public function getSousCategoriesVehicules(): Collection
    {
        return $this->sousCategoriesVehicules;
    }

    public function addSousCategoriesVehicule(SousCategoriesVehicules $sousCategoriesVehicule): self
    {
        if (!$this->sousCategoriesVehicules->contains($sousCategoriesVehicule)) {
            $this->sousCategoriesVehicules[] = $sousCategoriesVehicule;
            $sousCategoriesVehicule->setParc($this);
        }

        return $this;
    }

    public function removeSousCategoriesVehicule(SousCategoriesVehicules $sousCategoriesVehicule): self
    {
        if ($this->sousCategoriesVehicules->contains($sousCategoriesVehicule)) {
            $this->sousCategoriesVehicules->removeElement($sousCategoriesVehicule);
            // set the owning side to null (unless already changed)
            if ($sousCategoriesVehicule->getParc() === $this) {
                $sousCategoriesVehicule->setParc(null);
            }
        }

        return $this;
    }
}
