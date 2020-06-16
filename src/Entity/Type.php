<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TypeRepository")
 */
class Type
{
    // types de la catégorie article
	// CEA
    const LABEL_CSP = 'CSP';
    const LABEL_PDT = 'PDT';
    const LABEL_SILI = 'SILI';
    const LABEL_SILICIUM = 'SILICIUM';
    const LABEL_SILI_EXT = 'SILI-ext';
    const LABEL_SILI_INT = 'SILI-int';
    const LABEL_MOB = 'MOB';
    const LABEL_SLUGCIBLE = 'SLUGCIBLE';
    // type de la catégorie réception
    const LABEL_RECEPTION = 'RECEPTION';
    // types de la catégorie litige
	// Safran Ceramics
    const LABEL_MANQUE_BL = 'manque BL';
    const LABEL_MANQUE_INFO_BL = 'manque info BL';
    const LABEL_ECART_QTE = 'écart quantité + ou -';
    const LABEL_ECART_QUALITE = 'écart qualité';
    const LABEL_PB_COMMANDE = 'problème de commande';
    const LABEL_DEST_NON_IDENT = 'destinataire non identifiable';
	// types de la catégorie demande de livraison
	const LABEL_STANDARD = 'standard';

	/**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $label;

	/**
	 * @ORM\Column(type="text", nullable=true)
	 */
    private $description;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ChampLibre", mappedBy="type")
     */
    private $champsLibres;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ReferenceArticle", mappedBy="type")
     */
    private $referenceArticles;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Article", mappedBy="type")
     */
    private $articles;


    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\CategoryType", inversedBy="types")
     */
    private $category;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Reception", mappedBy="type")
     */
    private $receptions;

	/**
	 * @ORM\OneToMany(targetEntity="App\Entity\Demande", mappedBy="type")
	 */
	private $demandesLivraison;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Litige", mappedBy="type")
     */
    private $litiges;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Collecte", mappedBy="type")
     */
    private $collectes;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Utilisateur", mappedBy="types")
     */
    private $utilisateurs;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $sendMail;

    public function __construct()
    {
        $this->champsLibres = new ArrayCollection();
        $this->referenceArticles = new ArrayCollection();
        $this->articles = new ArrayCollection();
        $this->receptions = new ArrayCollection();
        $this->litiges = new ArrayCollection();
        $this->demandesLivraison = new ArrayCollection();
        $this->collectes = new ArrayCollection();
        $this->utilisateurs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @return Collection|ChampLibre[]
     */
    public function getChampsLibres(): Collection
    {
        return $this->champsLibres;
    }

    public function addChampLibre(ChampLibre $champLibre): self
    {
        if (!$this->champsLibres->contains($champLibre)) {
            $this->champsLibres[] = $champLibre;
            $champLibre->setType($this);
        }

        return $this;
    }

    public function removeChampLibre(ChampLibre $champLibre): self
    {
        if ($this->champsLibres->contains($champLibre)) {
            $this->champsLibres->removeElement($champLibre);
            // set the owning side to null (unless already changed)
            if ($champLibre->getType() === $this) {
                $champLibre->setType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|ReferenceArticle[]
     */
    public function getReferenceArticles(): Collection
    {
        return $this->referenceArticles;
    }

    public function addReferenceArticle(ReferenceArticle $referenceArticle): self
    {
        if (!$this->referenceArticles->contains($referenceArticle)) {
            $this->referenceArticles[] = $referenceArticle;
            $referenceArticle->setType($this);
        }

        return $this;
    }

    public function removeReferenceArticle(ReferenceArticle $referenceArticle): self
    {
        if ($this->referenceArticles->contains($referenceArticle)) {
            $this->referenceArticles->removeElement($referenceArticle);
            // set the owning side to null (unless already changed)
            if ($referenceArticle->getType() === $this) {
                $referenceArticle->setType(null);
            }
        }

        return $this;
    }

    public function getCategory(): ?CategoryType
    {
        return $this->category;
    }

    public function setCategory(?CategoryType $category): self
    {
        $this->category = $category;

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
            $article->setType($this);
        }

        return $this;
    }
    public function removeArticle(Article $article): self
    {
        if ($this->articles->contains($article)) {
            $this->articles->removeElement($article);
            // set the owning side to null (unless already changed)
            if ($article->getType() === $this) {
                $article->setType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Reception[]
     */
    public function getReceptions(): Collection
    {
        return $this->receptions;
    }

    public function addReception(Reception $reception): self
    {
        if (!$this->receptions->contains($reception)) {
            $this->receptions[] = $reception;
            $reception->setType($this);
        }

        return $this;
    }

    public function removeReception(Reception $reception): self
    {
        if ($this->receptions->contains($reception)) {
            $this->receptions->removeElement($reception);
            // set the owning side to null (unless already changed)
            if ($reception->getType() === $this) {
                $reception->setType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Litige[]
     */
    public function getLitiges(): Collection
    {
        return $this->litiges;
    }

    public function addCommentaire(Litige $commentaire): self
    {
        if (!$this->litiges->contains($commentaire)) {
            $this->litiges[] = $commentaire;
            $commentaire->setType($this);
        }

        return $this;
    }

    public function removeCommentaire(Litige $commentaire): self
    {
        if ($this->litiges->contains($commentaire)) {
            $this->litiges->removeElement($commentaire);
            // set the owning side to null (unless already changed)
            if ($commentaire->getType() === $this) {
                $commentaire->setType(null);
            }
        }

        return $this;
    }

    public function addLitige(Litige $litige): self
    {
        if (!$this->litiges->contains($litige)) {
            $this->litiges[] = $litige;
            $litige->setType($this);
        }

        return $this;
    }

    public function removeLitige(Litige $litige): self
    {
        if ($this->litiges->contains($litige)) {
            $this->litiges->removeElement($litige);
            // set the owning side to null (unless already changed)
            if ($litige->getType() === $this) {
                $litige->setType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Demande[]
     */
    public function getDemandesLivraison(): Collection
    {
        return $this->demandesLivraison;
    }

    public function addDemandesLivraison(Demande $demandesLivraison): self
    {
        if (!$this->demandesLivraison->contains($demandesLivraison)) {
            $this->demandesLivraison[] = $demandesLivraison;
            $demandesLivraison->setType($this);
        }

        return $this;
    }

    public function removeDemandesLivraison(Demande $demandesLivraison): self
    {
        if ($this->demandesLivraison->contains($demandesLivraison)) {
            $this->demandesLivraison->removeElement($demandesLivraison);
            // set the owning side to null (unless already changed)
            if ($demandesLivraison->getType() === $this) {
                $demandesLivraison->setType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Collecte[]
     */
    public function getCollectes(): Collection
    {
        return $this->collectes;
    }

    public function addCollecte(Collecte $collecte): self
    {
        if (!$this->collectes->contains($collecte)) {
            $this->collectes[] = $collecte;
            $collecte->setType($this);
        }

        return $this;
    }

    public function removeCollecte(Collecte $collecte): self
    {
        if ($this->collectes->contains($collecte)) {
            $this->collectes->removeElement($collecte);
            // set the owning side to null (unless already changed)
            if ($collecte->getType() === $this) {
                $collecte->setType(null);
            }
        }

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function addChampsLibre(ChampLibre $champsLibre): self
    {
        if (!$this->champsLibres->contains($champsLibre)) {
            $this->champsLibres[] = $champsLibre;
            $champsLibre->setType($this);
        }

        return $this;
    }

    public function removeChampsLibre(ChampLibre $champsLibre): self
    {
        if ($this->champsLibres->contains($champsLibre)) {
            $this->champsLibres->removeElement($champsLibre);
            // set the owning side to null (unless already changed)
            if ($champsLibre->getType() === $this) {
                $champsLibre->setType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Utilisateur[]
     */
    public function getUtilisateurs(): Collection
    {
        return $this->utilisateurs;
    }

    public function addUtilisateur(Utilisateur $utilisateur): self
    {
        if (!$this->utilisateurs->contains($utilisateur)) {
            $this->utilisateurs[] = $utilisateur;
            $utilisateur->addType($this);
        }

        return $this;
    }

    public function removeUtilisateur(Utilisateur $utilisateur): self
    {
        if ($this->utilisateurs->contains($utilisateur)) {
            $this->utilisateurs->removeElement($utilisateur);
            $utilisateur->removeType($this);
        }

        return $this;
    }

    public function getSendMail(): ?bool
    {
        return $this->sendMail;
    }

    public function setSendMail(?bool $sendMail): self
    {
        $this->sendMail = $sendMail;

        return $this;
    }
}
