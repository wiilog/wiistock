<?php

namespace App\Entity;

use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\DemandeRepository")
 */
class Demande extends FreeFieldEntity
{
    const CATEGORIE = 'demande';

    const STATUT_BROUILLON = 'brouillon';
    const STATUT_PREPARE = 'préparé';
	const STATUT_INCOMPLETE = 'partiellement préparé';
    const STATUT_A_TRAITER = 'à traiter';
    const STATUT_LIVRE = 'livré';
    const STATUT_LIVRE_INCOMPLETE = 'livré partiellement';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=false, unique=true)
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
     * @var Collection
     * @ORM\OneToMany(targetEntity="App\Entity\Preparation", mappedBy="demande")
     */
    private $preparations;

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
        $this->preparations = new ArrayCollection();
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

    public function getDate(): ?DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(?DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    /**
     * @return Collection|Preparation[]
     */
    public function getPreparations(): Collection {
        return $this->preparations;
    }

    public function addPreparation(?Preparation $preparation): self
    {
        if (!$this->preparations->contains($preparation)) {
            $this->preparations[] = $preparation;
            $preparation->setDemande($this);
        }

        return $this;
    }

    public function removePreparation(?Preparation $preparation): self
    {
        if (!$this->preparations->contains($preparation)) {
            $this->preparations->removeElement($preparation);
            // set the owning side to null (unless already changed)
            if ($preparation->getDemande() === $this) {
                $preparation->setDemande(null);
            }
        }

        return $this;
    }
    /**
     * @return Livraison[]|Collection
     */
    public function getLivraisons(): Collection
    {
        return $this->getPreparations()->map(function (Preparation $preparation) {
            return $preparation->getLivraison();
        })->filter(function(?Livraison $livraison) {
            return isset($livraison);
        });
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

    public function needsToBeProcessed(): bool {
        $demandeStatus = $this->getStatut();
        return (
            $demandeStatus
            && (
                $demandeStatus->getNom() === Demande::STATUT_A_TRAITER
                || $demandeStatus->getNom() === Demande::STATUT_PREPARE
            )
        );
    }

    public function getValidationDate(): ?DateTime {
        $preparationOrders = $this->getPreparations();
        return (!$preparationOrders->isEmpty())
            ? $preparationOrders->first()->getDate()
            : null;
    }

}
