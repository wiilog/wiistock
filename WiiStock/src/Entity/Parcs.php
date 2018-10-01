<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ParcsRepository")
 * @UniqueEntity(fields="n_parc", message="Ce numéro de parc est déjà attribué.")
 * @UniqueEntity(fields="n_serie", message="Ce numéro de série est déjà attribué.")
 * @UniqueEntity(fields="immatriculation", message="Ce numéro d'immatriculation est déjà attribué.")
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
	private $statut;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true, unique=true)
	 * @Groups({"parc"})
	 */
	private $n_parc;

/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Filiales", inversedBy="parcs")
	 * @ORM\JoinColumn(nullable=false)
	 * @Groups({"parc"})
	 */
	private $filiale;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Sites", inversedBy="parcs")
	 * @ORM\JoinColumn(nullable=false)
	 * @ORM\OrderBy({"nom" = "ASC"})
	 * @Groups({"parc"})
	 */
	private $site;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\CategoriesVehicules", inversedBy="parcs")
	 * @ORM\JoinColumn(nullable=false)
	 * @Groups({"parc"})
	 */
	private $categorieVehicule;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\SousCategoriesVehicules", inversedBy="parcs")
	 * @ORM\JoinColumn(nullable=false)
	 * @ORM\OrderBy({"nom" = "ASC"})
	 * @Groups({"parc"})
	 */
	private $sousCategorieVehicule;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Marques", inversedBy="parcs")
	 * @ORM\JoinColumn(nullable=false)
	 * @ORM\OrderBy({"nom" = "ASC"})
	 * @Groups({"parc"})
	 */
	private $marque;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 * @Groups({"parc"})
	 */
	private $modele;

	/**
	 * @ORM\Column(type="float", nullable=false)
	 * @Groups({"parc"})
	 */
	private $poids;

	/**
	 * @ORM\Column(type="date", nullable=true)
	 * @Groups({"parc"})
	 */
	private $mise_en_circulation;

	/**
	 * @ORM\Column(type="text", nullable=true)
	 * @Groups({"parc"})
	 */
	private $commentaire;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 * @Groups({"parc"})
	 */
	private $fournisseur;

	/**
	 * @ORM\Column(type="string", length=255, nullable=false)
	 * @Groups({"parc"})
	 */
	private $mode_acquisition;

	/**
	 * @ORM\Column(type="date", nullable=false)
	 * @Groups({"parc"})
	 */
	private $mise_en_service;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 * @Groups({"parc"})
	 */
	private $n_serie;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 * @Groups({"parc"})
	 */
	private $immatriculation;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 * @Groups({"parc"})
	 */
	private $genre;

	/**
	 * @ORM\Column(type="float", nullable=true)
	 * @Groups({"parc"})
	 */
	private $ptac;

	/**
	 * @ORM\Column(type="float", nullable=true)
	 * @Groups({"parc"})
	 */
	private $ptr;

	/**
	 * @ORM\Column(type="integer", nullable=true)
	 * @Groups({"parc"})
	 */
	private $puissance_fiscale;

	/**
	 * @ORM\Column(type="date", nullable=true)
	 * @Groups({"parc"})
	 */
	private $sortie;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 * @Groups({"parc"})
	 */
	private $motif;

	/**
	 * @ORM\Column(type="text", nullable=true)
	 * @Groups({"parc"})
	 */
	private $commentaire_sortie;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 */
	private $img;

	/**
	 * @ORM\Column(type="boolean", nullable=true)
	 */
	private $estSorti;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 */
	private $lastEdit;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $img_origine;

	public function getId() : ? int
         	{
         		return $this->id;
         	}

	public function getModele() : ? string
         	{
         		return $this->modele;
         	}

	public function setModele(? string $modele) : self
         	{
         		$this->modele = $modele;
         
         		return $this;
         	}

	public function getStatut() : ? string
         	{
         		return $this->statut;
         	}

	public function setStatut(? string $statut) : self
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

	public function setMiseEnCirculation(? \DateTimeInterface $mise_en_circulation) : self
         	{
         		$this->mise_en_circulation = $mise_en_circulation;
         
         		return $this;
         	}

	public function getFournisseur() : ? string
         	{
         		return $this->fournisseur;
         	}

	public function setFournisseur(? string $fournisseur) : self
         	{
         		$this->fournisseur = $fournisseur;
         
         		return $this;
         	}

	public function getPoids() : ? float
         	{
         		return $this->poids;
         	}

	public function setPoids(float $poids) : self
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

	public function setCommentaire(? string $commentaire) : self
         	{
         		$this->commentaire = $commentaire;
         
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

	public function setSortie(? \DateTimeInterface $sortie) : self
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

	public function setCommentaireSortie(? string $commentaire_sortie) : self
         	{
         		$this->commentaire_sortie = $commentaire_sortie;
         
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

	public function getNSerie(): ?string
         	{
         		return $this->n_serie;
         	}

	public function setNSerie(?string $n_serie): self
         	{
         		$this->n_serie = $n_serie;
         
         		return $this;
         	}

	public function getImmatriculation(): ?string
         	{
         		return $this->immatriculation;
         	}

	public function setImmatriculation(?string $immatriculation): self
         	{
         		$this->immatriculation = $immatriculation;
         
         		return $this;
         	}

	public function getGenre(): ?string
         	{
         		return $this->genre;
         	}

	public function setGenre(?string $genre): self
         	{
         		$this->genre = $genre;
         
         		return $this;
         	}

	public function getPtac(): ?float
         	{
         		return $this->ptac;
         	}

	public function setPtac(float $ptac): self
         	{
         		$this->ptac = $ptac;
         
         		return $this;
         	}

	public function getPtr(): ?float
         	{
         		return $this->ptr;
         	}

	public function setPtr(float $ptr): self
         	{
         		$this->ptr = $ptr;
         
         		return $this;
         	}

	public function getPuissanceFiscale(): ?int
         	{
         		return $this->puissance_fiscale;
         	}

	public function setPuissanceFiscale(int $puissance_fiscale): self
         	{
         		$this->puissance_fiscale = $puissance_fiscale;
         
         		return $this;
         	}

	public function getImg(): ?string
         	{
         		return $this->img;
         	}

	public function setImg(?string $img): self
         	{
         		$this->img = $img;
         
         		return $this;
         	}

	public function getEstSorti(): ?bool
         	{
         		return $this->estSorti;
         	}

	public function setEstSorti(?bool $estSorti): self
         	{
         		$this->estSorti = $estSorti;
         
         		return $this;
         	}

	public function getLastEdit(): ?string
         	{
         		return $this->lastEdit;
         	}

	public function setLastEdit(?string $lastEdit): self
         	{
         		$this->lastEdit = $lastEdit;
         
         		return $this;
         	}

    public function getImgOrigine(): ?string
    {
        return $this->img_origine;
    }

    public function setImgOrigine(?string $img_origine): self
    {
        $this->img_origine = $img_origine;

        return $this;
    }
}
