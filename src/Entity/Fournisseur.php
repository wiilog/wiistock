<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\FournisseurRepository")
 */
class Fournisseur
{
    const REF_A_DEFINIR = 'A DEFINIR';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private ?string $codeReference = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $nom = null;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Reception", mappedBy="fournisseur")
     */
    private Collection $receptions;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ArticleFournisseur", mappedBy="fournisseur")
     */
    private Collection $articlesFournisseur;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ReceptionReferenceArticle", mappedBy="fournisseur")
     */
    private Collection $receptionReferenceArticles;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Arrivage", mappedBy="fournisseur")
     */
    private Collection $arrivages;

    /**
     * @ORM\OneToMany(targetEntity=PurchaseRequestLine::class, mappedBy="supplier")
     */
    private Collection $purchaseRequestLines;

    /**
     * @ORM\Column(type="boolean", options={"default": false})
     */
    private ?bool $isUrgent = null;

    /**
     * @ORM\Column(type="boolean", options={"default": false})
     */
    private ?bool $isPossibleCustoms = null;

    public function __construct()
    {
        $this->receptions = new ArrayCollection();
        $this->articlesFournisseur = new ArrayCollection();
        $this->receptionReferenceArticles = new ArrayCollection();
        $this->arrivages = new ArrayCollection();
        $this->purchaseRequestLines = new ArrayCollection();
    }

    public function getId() : ? int
    {
        return $this->id;
    }

    public function getCodeReference() : ? string
    {
        return $this->codeReference;
    }

    public function setCodeReference(? string $codeReference) : self
    {
        $this->codeReference = $codeReference;

        return $this;
    }

    public function getNom() : ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom) : self
    {
        $this->nom = $nom;

        return $this;
    }

    /**
     * @return Collection|Reception[]
     */
    public function getReceptions() : Collection
    {
        return $this->receptions;
    }

    public function addReceptions(Reception $reception) : self
    {
        if (!$this->receptions->contains($reception)) {
            $this->receptions[] = $reception;
            $reception->setFournisseur($this);
        }

        return $this;
    }

    public function removeReceptions(Reception $reception) : self
    {
        if ($this->receptions->contains($reception)) {
            $this->receptions->removeElement($reception);
            // set the owning side to null (unless already changed)
            if ($reception->getFournisseur() === $this) {
                $reception->setFournisseur(null);
            }
        }

        return $this;
    }

    public function __toString()
    {
        return $this->nom;
    }

    public function addReception(Reception $reception): self
    {
        if (!$this->receptions->contains($reception)) {
            $this->receptions[] = $reception;
            $reception->setFournisseur($this);
        }

        return $this;
    }

    public function removeReception(Reception $reception): self
    {
        if ($this->receptions->contains($reception)) {
            $this->receptions->removeElement($reception);
            // set the owning side to null (unless already changed)
            if ($reception->getFournisseur() === $this) {
                $reception->setFournisseur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|ArticleFournisseur[]
     */
    public function getArticlesFournisseur(): Collection
    {
        return $this->articlesFournisseur;
    }

    public function addArticlesFournisseur(ArticleFournisseur $articlesFournisseur): self
    {
        if (!$this->articlesFournisseur->contains($articlesFournisseur)) {
            $this->articlesFournisseur[] = $articlesFournisseur;
            $articlesFournisseur->setFournisseur($this);
        }

        return $this;
    }

    public function removeArticlesFournisseur(ArticleFournisseur $articlesFournisseur): self
    {
        if ($this->articlesFournisseur->contains($articlesFournisseur)) {
            $this->articlesFournisseur->removeElement($articlesFournisseur);
            // set the owning side to null (unless already changed)
            if ($articlesFournisseur->getFournisseur() === $this) {
                $articlesFournisseur->setFournisseur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|ReceptionReferenceArticle[]
     */
    public function getReceptionReferenceArticles(): Collection
    {
        return $this->receptionReferenceArticles;
    }

    public function addReceptionReferenceArticle(ReceptionReferenceArticle $receptionReferenceArticle): self
    {
        if (!$this->receptionReferenceArticles->contains($receptionReferenceArticle)) {
            $this->receptionReferenceArticles[] = $receptionReferenceArticle;
            $receptionReferenceArticle->setFournisseur($this);
        }

        return $this;
    }

    public function removeReceptionReferenceArticle(ReceptionReferenceArticle $receptionReferenceArticle): self
    {
        if ($this->receptionReferenceArticles->contains($receptionReferenceArticle)) {
            $this->receptionReferenceArticles->removeElement($receptionReferenceArticle);
            // set the owning side to null (unless already changed)
            if ($receptionReferenceArticle->getFournisseur() === $this) {
                $receptionReferenceArticle->setFournisseur(null);
            }
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
            $arrivage->setFournisseur($this);
        }

        return $this;
    }

    public function removeArrivage(Arrivage $arrivage): self
    {
        if ($this->arrivages->contains($arrivage)) {
            $this->arrivages->removeElement($arrivage);
            // set the owning side to null (unless already changed)
            if ($arrivage->getFournisseur() === $this) {
                $arrivage->setFournisseur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|PurchaseRequestLine[]
     */
    public function getPurchaseRequestLines(): Collection {
        return $this->purchaseRequestLines;
    }

    public function addPurchaseRequestLine(PurchaseRequestLine $purchaseRequestLine): self {
        if (!$this->purchaseRequestLines->contains($purchaseRequestLine)) {
            $this->purchaseRequestLines[] = $purchaseRequestLine;
            $purchaseRequestLine->setSupplier($this);
        }

        return $this;
    }

    public function removePurchaseRequestLine(PurchaseRequestLine $purchaseRequestLine): self {
        if ($this->purchaseRequestLines->removeElement($purchaseRequestLine)) {
            if ($purchaseRequestLine->getSupplier() === $this) {
                $purchaseRequestLine->setSupplier(null);
            }
        }

        return $this;
    }

    public function setPurchaseRequestLines(?array $purchaseRequestLines): self {
        foreach($this->getPurchaseRequestLines()->toArray() as $purchaseRequestLine) {
            $this->removePurchaseRequestLine($purchaseRequestLine);
        }

        $this->purchaseRequestLines = new ArrayCollection();
        foreach($purchaseRequestLines as $purchaseRequestLine) {
            $this->addPurchaseRequestLine($purchaseRequestLine);
        }

        return $this;
    }

    public function isUrgent() {
        return $this->isUrgent;
    }

    public function setIsUrgent(?bool $isUrgent): self
    {
        $this->isUrgent = $isUrgent;

        return $this;
    }

    public function isPossibleCustoms() {
        return $this->isPossibleCustoms;
    }

    public function setIsPossibleCustoms(?bool $isPossibleCustoms): self
    {
        $this->isPossibleCustoms = $isPossibleCustoms;

        return $this;
    }
}
