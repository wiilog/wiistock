<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\DemandeRepository")
 */
class Demande
{
    const CATEGORIE = 'demande';

    const STATUT_BROUILLON = 'brouillon';
    const STATUT_PREPARE = 'préparé';
	const STATUT_INCOMPLETE = 'partiellement préparé';
    const STATUT_A_TRAITER = 'à traiter';
    const STATUT_LIVRE = 'livré';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $numero;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacement", inversedBy="demandes")
     */
    private $destination;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="demandes")
     */
    private $utilisateur;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $date;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Preparation", inversedBy="demandes")
     */
    private $preparation;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Livraison", inversedBy="demande")
     */
    private $livraison;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Statut", inversedBy="demandes")
     */
    private $statut;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Type", inversedBy="demandesLivraison")
	 */
    private $type;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\LigneArticle", mappedBy="demande")
     */
    private $ligneArticle;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $commentaire;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Article", mappedBy="demande")
     */
    private $articles;

	/**
	 * @ORM\ManyToMany(targetEntity="App\Entity\ValeurChampLibre", inversedBy="demandesLivraison")
	 */
	private $valeurChampLibre;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Reception", inversedBy="demandes")
     */
    private $reception;


	public function __construct() {
        $this->ligneArticle = new ArrayCollection();
        $this->articles = new ArrayCollection();
        $this->valeurChampLibre = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(?string $numero): self
    {
        $this->numero = $numero;

        return $this;
    }

    public function getDestination(): ?Emplacement
    {
        return $this->destination;
    }

    public function setDestination(?Emplacement $destination): self
    {
        $this->destination = $destination;

        return $this;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): self
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(?\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getPreparation(): ?Preparation
    {
        return $this->preparation;
    }

    public function setPreparation(?Preparation $preparation): self
    {
        $this->preparation = $preparation;

        return $this;
    }

    public function getLivraison(): ?Livraison
    {
        return $this->getPreparation()
                ? (!empty($this->getPreparation()->getLivraisons())
                    ? $this->getPreparation()->getLivraisons()[0]
                    : null)
                : null;
    }

    public function setLivraison(?Livraison $livraison): self
    {
        $this->livraison = $livraison;

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




//    public function getDateAttendu(): ?\DateTimeInterface
//    {
//        return $this->DateAttendu;
//    }
//
//    public function setDateAttendu(?\DateTimeInterface $DateAttendu): self
//    {
//        $this->DateAttendu = $DateAttendu;
//
//        return $this;
//    }

    /**
     * @return Collection|LigneArticle[]
     */
    public function getLigneArticle(): Collection
    {
        return $this->ligneArticle;
    }

    public function addLigneArticle(LigneArticle $ligneArticle): self
    {
        if (!$this->ligneArticle->contains($ligneArticle)) {
            $this->ligneArticle[] = $ligneArticle;
            $ligneArticle->setDemande($this);
        }

        return $this;
    }

    public function removeLigneArticle(LigneArticle $ligneArticle): self
    {
        if ($this->ligneArticle->contains($ligneArticle)) {
            $this->ligneArticle->removeElement($ligneArticle);
            // set the owning side to null (unless already changed)
            if ($ligneArticle->getDemande() === $this) {
                $ligneArticle->setDemande(null);
            }
        }

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    /**
     * @return Collection|Article[]
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(Article $article): self
    {
        if (!$this->articles->contains($article)) {
            $this->articles[] = $article;
            $article->setDemande($this);
        }

        return $this;
    }

    public function removeArticle(Article $article): self
    {
        if ($this->articles->contains($article)) {
            $this->articles->removeElement($article);
            if ($article->getDemande() === $this) {
                $article->setDemande(null);
            }
        }

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

    /**
     * @return Collection|ValeurChampLibre[]
     */
    public function getValeurChampLibre(): Collection
    {
        return $this->valeurChampLibre;
    }

    public function addValeurChampLibre(ValeurChampLibre $valeurChampLibre): self
    {
        if (!$this->valeurChampLibre->contains($valeurChampLibre)) {
            $this->valeurChampLibre[] = $valeurChampLibre;
        }

        return $this;
    }

    public function removeValeurChampLibre(ValeurChampLibre $valeurChampLibre): self
    {
        if ($this->valeurChampLibre->contains($valeurChampLibre)) {
            $this->valeurChampLibre->removeElement($valeurChampLibre);
        }

        return $this;
    }

    public function getReception(): ?Reception
    {
        return $this->reception;
    }

    public function setReception(?Reception $reception): self
    {
        $this->reception = $reception;

        return $this;
    }


}
