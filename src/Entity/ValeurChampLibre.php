<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ValeurChampLibreRepository")
 */
class ValeurChampLibre
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $valeur;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\ReferenceArticle", inversedBy="valeurChampsLibres")
     */
    private $articleReference;
    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Article", inversedBy="valeurChampsLibres")
     */
    private $article;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ChampLibre", inversedBy="valeurChampsLibres")
     * @ORM\JoinColumn(name="champ_libre_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private $champLibre;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Reception", mappedBy="valeurChampLibre")
     */
    private $receptions;

	/**
	 * @ORM\ManyToMany(targetEntity="App\Entity\Demande", mappedBy="valeurChampLibre")
	 */
    private $demandesLivraison;

	/**
	 * @ORM\ManyToMany(targetEntity="App\Entity\Collecte", mappedBy="valeurChampLibre")
	 */
	private $demandesCollecte;

	/**
	 * @ORM\ManyToMany(targetEntity="App\Entity\Arrivage", mappedBy="valeurChampLibre")
	 */
	private $arrivages;

    public function __construct()
    {
        $this->champLibre = new ArrayCollection();
        $this->articleReference = new ArrayCollection();
        $this->article = new ArrayCollection();
        $this->receptions = new ArrayCollection();
        $this->demandesLivraison = new ArrayCollection();
        $this->demandesCollecte = new ArrayCollection();
        $this->arrivages = new ArrayCollection();
    }


    public function __toString()
    {
        return $this->valeur;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getValeur(): ?string
    {
        return $this->valeur;
    }

    public function setValeur(?string $valeur): self
    {
        $this->valeur = $valeur;

        return $this;
    }

    /**
     * @return Collection|ReferenceArticle[]
     */
    public function getArticleReference(): Collection
    {
        return $this->articleReference;
    }

    public function addArticleReference(ReferenceArticle $articleReference): self
    {
        if (!$this->articleReference->contains($articleReference)) {
            $this->articleReference[] = $articleReference;
        }

        return $this;
    }

    public function removeArticleReference(ReferenceArticle $articleReference): self
    {
        if ($this->articleReference->contains($articleReference)) {
            $this->articleReference->removeElement($articleReference);
        }

        return $this;
    }

    public function getChampLibre(): ?ChampLibre
    {
        return $this->champLibre;
    }

    public function setChampLibre(?ChampLibre $champLibre): self
    {
        $this->champLibre = $champLibre;

        return $this;
    }


    /**
    * @return Collection|Article[]
    */
    public function getArticle(): Collection
    {
        return $this->article;
    }

    public function addArticle(Article $article): self
    {
        if (!$this->article->contains($article)) {
            $this->article[] = $article;
        }

        return $this;
    }

    public function removeArticle(Article $article): self
    {
        if ($this->article->contains($article)) {
            $this->article->removeElement($article);
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
            $reception->addValeurChampLibre($this);
        }

        return $this;
    }

    public function removeReception(Reception $reception): self
    {
        if ($this->receptions->contains($reception)) {
            $this->receptions->removeElement($reception);
            $reception->removeValeurChampLibre($this);
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
            $demandesLivraison->addValeurChampLibre($this);
        }

        return $this;
    }

    public function removeDemandesLivraison(Demande $demandesLivraison): self
    {
        if ($this->demandesLivraison->contains($demandesLivraison)) {
            $this->demandesLivraison->removeElement($demandesLivraison);
            $demandesLivraison->removeValeurChampLibre($this);
        }

        return $this;
    }

    /**
     * @return Collection|Collecte[]
     */
    public function getDemandesCollecte(): Collection
    {
        return $this->demandesCollecte;
    }

    public function addDemandesCollecte(Collecte $demandesCollecte): self
    {
        if (!$this->demandesCollecte->contains($demandesCollecte)) {
            $this->demandesCollecte[] = $demandesCollecte;
            $demandesCollecte->addValeurChampLibre($this);
        }

        return $this;
    }

    public function removeDemandesCollecte(Collecte $demandesCollecte): self
    {
        if ($this->demandesCollecte->contains($demandesCollecte)) {
            $this->demandesCollecte->removeElement($demandesCollecte);
            $demandesCollecte->removeValeurChampLibre($this);
        }

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
            $arrivage->addValeurChampLibre($this);
        }

        return $this;
    }

    public function removeArrivage(Arrivage $arrivage): self
    {
        if ($this->arrivages->contains($arrivage)) {
            $this->arrivages->removeElement($arrivage);
			$arrivage->removeValeurChampLibre($this);
		}

        return $this;
    }
}