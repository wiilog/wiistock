<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Serializer\Annotation\Groups;

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
     * @Groups({"parc"})
     */
    private $modele;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"parc"})
     */
    private $statut;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"parc"})
     */
    private $n_parc;

    /**
     * @ORM\Column(type="date")
     * @Groups({"parc"})
     */
    private $mise_en_circulation;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"parc"})
     */
    private $fournisseur;

    /**
     * @ORM\Column(type="integer")
     * @Groups({"parc"})
     */
    private $poids;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"parc"})
     */
    private $mode_acquisition;

    /**
     * @ORM\Column(type="text")
     * @Groups({"parc"})
     */
    private $commentaire;

    /**
     * @ORM\Column(type="date")
     * @Groups({"parc"})
     */
    private $incorporation;

    /**
     * @ORM\Column(type="date")
     * @Groups({"parc"})
     */
    private $mise_en_service;

    /**
     * @ORM\Column(type="date")
     * @Groups({"parc"})
     */
    private $sortie;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"parc"})
     */
    private $motif;

    /**
     * @ORM\Column(type="text")
     * @Groups({"parc"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $commentaire_sortie;

    /** 
     * @ORM\OneToOne(targetEntity="App\Entity\Chariots", mappedBy="parc", cascade={"persist", "remove"})
     */
    private $chariots;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Vehicules", mappedBy="parc", cascade={"persist", "remove"})
     * @Groups({"parc"})
     */
    private $vehicules;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Filiales", inversedBy="parcs")
     * @Groups({"parc"})
     */
    private $filiale;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Marques", inversedBy="parcs")
     * @Groups({"parc"})
     */
    private $marque;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Sites", inversedBy="parcs")
     * @Groups({"parc"})
     */
    private $site;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\SousCategoriesVehicules", inversedBy="parcs")
     * @Groups({"parc"})
     */
    private $sousCategorieVehicule;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\CategoriesVehicules", inversedBy="parcs")
     * @Groups({"parc"})
     */
    private $categorieVehicule;

    public function getId() : ? int
    {
        return $this->id;
    }

    public function getModele() : ? string
    {
        return $this->modele;
    }

    public function setModele(string $modele) : self
    {
        $this->modele = $modele;

        return $this;
    }

    public function getStatut() : ? string
    {
        return $this->statut;
    }

    public function setStatut(string $statut) : self
    {
        $this->statut = $statut;

        return $this;
    }

    public function getNParc() : ? string
    {
        return $this->n_parc;
    }

    public function setNParc(string $n_parc) : self
    {
        $this->n_parc = $n_parc;

        return $this;
    }

    public function getMiseEnCirculation() : ? \DateTimeInterface
    {
        return $this->mise_en_circulation;
    }

    public function setMiseEnCirculation(\DateTimeInterface $mise_en_circulation) : self
    {
        $this->mise_en_circulation = $mise_en_circulation;

        return $this;
    }

    public function getFournisseur() : ? string
    {
        return $this->fournisseur;
    }

    public function setFournisseur(string $fournisseur) : self
    {
        $this->fournisseur = $fournisseur;

        return $this;
    }

    public function getPoids() : ? int
    {
        return $this->poids;
    }

    public function setPoids(int $poids) : self
    {
        $this->poids = $poids;

        return $this;
    }

    public function getModeAcquisition() : ? string
    {
        return $this->mode_acquisition;
    }

    public function setModeAcquisition(string $mode_acquisition) : self
    {
        $this->mode_acquisition = $mode_acquisition;

        return $this;
    }

    public function getCommentaire() : ? string
    {
        return $this->commentaire;
    }

    public function setCommentaire(string $commentaire) : self
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getIncorporation() : ? \DateTimeInterface
    {
        return $this->incorporation;
    }

    public function setIncorporation(\DateTimeInterface $incorporation) : self
    {
        $this->incorporation = $incorporation;

        return $this;
    }

    public function getMiseEnService() : ? \DateTimeInterface
    {
        return $this->mise_en_service;
    }

    public function setMiseEnService(\DateTimeInterface $mise_en_service) : self
    {
        $this->mise_en_service = $mise_en_service;

        return $this;
    }

    public function getSortie() : ? \DateTimeInterface
    {
        return $this->sortie;
    }

    public function setSortie(\DateTimeInterface $sortie) : self
    {
        $this->sortie = $sortie;

        return $this;
    }

    public function getMotif() : ? string
    {
        return $this->motif;
    }

    public function setMotif(string $motif) : self
    {
        $this->motif = $motif;

        return $this;
    }

    public function getCommentaireSortie() : ? string
    {
        return $this->commentaire_sortie;
    }

    public function setCommentaireSortie(string $commentaire_sortie) : self
    {
        $this->commentaire_sortie = $commentaire_sortie;

        return $this;
    }

    public function getChariots() : ? Chariots
    {
        return $this->chariots;
    }

    public function setChariots(? Chariots $chariots) : self
    {
        $this->chariots = $chariots;

        // set (or unset) the owning side of the relation if necessary
        $newParc = $chariots === null ? null : $this;
        if ($newParc !== $chariots->getParc()) {
            $chariots->setParc($newParc);
        }

        return $this;
    }

    public function getVehicules() : ? Vehicules
    {
        return $this->vehicules;
    }

    public function setVehicules(? Vehicules $vehicules) : self
    {
        $this->vehicules = $vehicules;

        // set (or unset) the owning side of the relation if necessary
        $newParc = $vehicules === null ? null : $this;
        if ($newParc !== $vehicules->getParc()) {
            $vehicules->setParc($newParc);
        }

        return $this;
    }

    public function getFiliale(): ?Filiales
    {
        return $this->filiale;
    }

    public function setFiliale(?Filiales $filiale): self
    {
        $this->filiale = $filiale;

        return $this;
    }

    public function getMarque(): ?Marques
    {
        return $this->marque;
    }

    public function setMarque(?Marques $marque): self
    {
        $this->marque = $marque;

        return $this;
    }

    public function getSite(): ?Sites
    {
        return $this->site;
    }

    public function setSite(?Sites $site): self
    {
        $this->site = $site;

        return $this;
    }

    public function getSousCategorieVehicule(): ?SousCategoriesVehicules
    {
        return $this->sousCategorieVehicule;
    }

    public function setSousCategorieVehicule(?SousCategoriesVehicules $sousCategorieVehicule): self
    {
        $this->sousCategorieVehicule = $sousCategorieVehicule;

        return $this;
    }

    public function getCategorieVehicule(): ?CategoriesVehicules
    {
        return $this->categorieVehicule;
    }

    public function setCategorieVehicule(?CategoriesVehicules $categorieVehicule): self
    {
        $this->categorieVehicule = $categorieVehicule;

        return $this;
    }
}
