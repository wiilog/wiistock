<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\StatutRepository")
 */
class Statut
{

    const DRAFT = 0;
    const NOT_TREATED = 1;
    const TREATED = 2;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $code;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $nom;

	/**
	 * @ORM\Column(type="text", nullable=true)
	 */
    private $comment;

	/**
	 * @ORM\Column(type="integer", nullable=true)
	 */
    private $displayOrder;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $state;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\CategorieStatut", inversedBy="statuts")
     */
    private $categorie;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Article", mappedBy="statut")
     */
    private $articles;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Reception", mappedBy="statut")
     */
    private $receptions;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Demande", mappedBy="statut")
     */
    private $demandes;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Preparation", mappedBy="statut")
     */
    private $preparations;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Livraison", mappedBy="statut")
     */
    private $livraisons;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Collecte", mappedBy="statut")
     */
    private $collectes;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ReferenceArticle", mappedBy="statut")
     */
    private $referenceArticles;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Handling", mappedBy="status")
     */
    private $handlings;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Dispatch", mappedBy="statut")
     */
    private $dispatches;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Litige", mappedBy="status")
     */
    private $litiges;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $sendNotifToBuyer;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Arrivage", mappedBy="statut")
     */
    private $arrivages;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $sendNotifToDeclarant;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Type", inversedBy="statuts")
     */
    private $type;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $sendNotifToRecipient;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $needsMobileSync;

    /**
     * @var bool
     * @ORM\Column(type="boolean", nullable=false, options={"default": false})
     */
    private $defaultForCategory;

    public function __construct()
    {
        $this->articles = new ArrayCollection();
        $this->receptions = new ArrayCollection();
        $this->demandes = new ArrayCollection();
        $this->preparations = new ArrayCollection();
        $this->livraisons = new ArrayCollection();
        $this->collectes = new ArrayCollection();
        $this->referenceArticles = new ArrayCollection();
        $this->handlings = new ArrayCollection();
        $this->litiges = new ArrayCollection();
        $this->dispatches = new ArrayCollection();
        $this->arrivages = new ArrayCollection();

        $this->defaultForCategory = false;
    }

    public function getId(): ? int
    {
        return $this->id;
    }

    public function getNom(): ? string
    {
        return $this->nom;
    }

    public function setNom(? string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function isTreated(): ?bool {
        return $this->state === self::TREATED;
    }

    public function isDraft(): ?bool {
        return $this->state === self::DRAFT;
    }

    public function isNotTreated(): ?bool {
        return $this->state === self::NOT_TREATED;
    }

    public function getCategorie(): ? CategorieStatut
    {
        return $this->categorie;
    }

    public function setCategorie(? CategorieStatut $categorie): self
    {
        $this->categorie = $categorie;

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
            $article->setStatut($this);
        }

        return $this;
    }

    public function removeArticle(Article $article): self
    {
        if ($this->articles->contains($article)) {
            $this->articles->removeElement($article);
            // set the owning side to null (unless already changed)
            if ($article->getStatut() === $this) {
                $article->setStatut(null);
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
            $reception->setStatut($this);
        }

        return $this;
    }

    public function removeReception(Reception $reception): self
    {
        if ($this->receptions->contains($reception)) {
            $this->receptions->removeElement($reception);
            // set the owning side to null (unless already changed)
            if ($reception->getStatut() === $this) {
                $reception->setStatut(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Demande[]
     */
    public function getDemandes(): Collection
    {
        return $this->demandes;
    }

    public function addDemande(Demande $demande): self
    {
        if (!$this->demandes->contains($demande)) {
            $this->demandes[] = $demande;
            $demande->setStatut($this);
        }

        return $this;
    }

    public function removeDemande(Demande $demande): self
    {
        if ($this->demandes->contains($demande)) {
            $this->demandes->removeElement($demande);
            // set the owning side to null (unless already changed)
            if ($demande->getStatut() === $this) {
                $demande->setStatut(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Preparation[]
     */
    public function getPreparations(): Collection
    {
        return $this->preparations;
    }

    public function addPreparation(Preparation $preparation): self
    {
        if (!$this->preparations->contains($preparation)) {
            $this->preparations[] = $preparation;
            $preparation->setStatut($this);
        }

        return $this;
    }

    public function removePreparation(Preparation $preparation): self
    {
        if ($this->preparations->contains($preparation)) {
            $this->preparations->removeElement($preparation);
            // set the owning side to null (unless already changed)
            if ($preparation->getStatut() === $this) {
                $preparation->setStatut(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Livraison[]
     */
    public function getLivraisons(): Collection
    {
        return $this->livraisons;
    }

    public function addLivraison(Livraison $livraison): self
    {
        if (!$this->livraisons->contains($livraison)) {
            $this->livraisons[] = $livraison;
            $livraison->setStatut($this);
        }

        return $this;
    }

    public function removeLivraison(Livraison $livraison): self
    {
        if ($this->livraisons->contains($livraison)) {
            $this->livraisons->removeElement($livraison);
            // set the owning side to null (unless already changed)
            if ($livraison->getStatut() === $this) {
                $livraison->setStatut(null);
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
            $collecte->setStatut($this);
        }

        return $this;
    }

    public function removeCollecte(Collecte $collecte): self
    {
        if ($this->collectes->contains($collecte)) {
            $this->collectes->removeElement($collecte);
            // set the owning side to null (unless already changed)
            if ($collecte->getStatut() === $this) {
                $collecte->setStatut(null);
            }
        }

        return $this;
    }

    public function __toString()
    {
        return $this->nom;
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
            $referenceArticle->setStatut($this);
        }
        return $this;
    }

    public function removeReferenceArticle(ReferenceArticle $referenceArticle): self
    {
        if ($this->referenceArticles->contains($referenceArticle)) {
            $this->referenceArticles->removeElement($referenceArticle);
            // set the owning side to null (unless already changed)
            if ($referenceArticle->getStatut() === $this) {
                $referenceArticle->setStatut(null);
            }
        }
        return $this;
    }


    /**
    * @return Collection|Handling[]
     */
    public function getHandlings(): Collection
    {
        return $this->handlings;
    }

    public function addHandling(Handling $handling): self
    {
        if (!$this->handlings->contains($handling)) {
            $this->handlings[] = $handling;
            $handling->setStatus($this);
        }

        return $this;
    }


    public function removeHandling(Handling $handling): self
    {
        if ($this->handlings->contains($handling)) {
            $this->handlings->removeElement($handling);
            // set the owning side to null (unless already changed)
            if ($handling->getStatus() === $this) {
                $handling->setStatus(null);
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

    public function addLitige(Litige $litige): self
    {
        if (!$this->litiges->contains($litige)) {
            $this->litiges[] = $litige;
            $litige->setStatus($this);
        }

        return $this;
    }

    public function removeLitige(Litige $litige): self
    {
        if ($this->litiges->contains($litige)) {
            $this->litiges->removeElement($litige);
            // set the owning side to null (unless already changed)
            if ($litige->getStatus() === $this) {
                $litige->setStatus(null);
            }
        }

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getDisplayOrder(): ?int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(int $displayOrder): self
    {
        $this->displayOrder = $displayOrder;

        return $this;
    }

    /**
     * @return Collection|Dispatch[]
     */
    public function getDispatches(): Collection
    {
        return $this->dispatches;
    }

    public function addDispatch(Dispatch $dispatch): self
    {
        if (!$this->dispatches->contains($dispatch)) {
            $this->dispatches[] = $dispatch;
            $dispatch->setStatut($this);
        }

        return $this;
    }

    public function removeDispatch(Dispatch $dispatch): self
    {
        if ($this->dispatches->contains($dispatch)) {
            $this->dispatches->removeElement($dispatch);
            // set the owning side to null (unless already changed)
            if ($dispatch->getStatut() === $this) {
                $dispatch->setStatut(null);
            }
        }

        return $this;
    }

    public function getSendNotifToBuyer(): ?bool
    {
        return $this->sendNotifToBuyer;
    }

    public function setSendNotifToBuyer(?bool $sendNotifToBuyer): self
    {
        $this->sendNotifToBuyer = $sendNotifToBuyer;

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
            $arrivage->setStatut($this);
        }

        return $this;
    }

    public function removeArrivage(Arrivage $arrivage): self
    {
        if ($this->arrivages->contains($arrivage)) {
            $this->arrivages->removeElement($arrivage);
            // set the owning side to null (unless already changed)
            if ($arrivage->getStatut() === $this) {
                $arrivage->setStatut(null);
            }
        }

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getSendNotifToDeclarant(): ?bool
    {
        return $this->sendNotifToDeclarant;
    }

    public function setSendNotifToDeclarant(?bool $sendNotifToDeclarant): self
    {
        $this->sendNotifToDeclarant = $sendNotifToDeclarant;

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

    public function getSendNotifToRecipient(): ?bool
    {
        return $this->sendNotifToRecipient;
    }

    public function setSendNotifToRecipient(?bool $sendNotifToRecipient): self
    {
        $this->sendNotifToRecipient = $sendNotifToRecipient;

        return $this;
    }

    public function getNeedsMobileSync(): ?bool
    {
        return $this->needsMobileSync;
    }

    public function setNeedsMobileSync(?bool $needsMobileSync): self
    {
        $this->needsMobileSync = $needsMobileSync;

        return $this;
    }

    /**
     * @return bool
     */
    public function isDefaultForCategory(): bool {
        return $this->defaultForCategory;
    }

    /**
     * @param bool $defaultForCategory
     * @return self
     */
    public function setDefaultForCategory(bool $defaultForCategory): self {
        $this->defaultForCategory = $defaultForCategory;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getState(): int {
        return $this->state;
    }

    /**
     * @param mixed $state
     * @return self
     */
    public function setState(int $state): self {
        $this->state = $state;
        return $this;
    }

}
