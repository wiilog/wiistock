<?php

namespace App\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\MouvementTracaRepository")
 */
class MouvementTraca
{

    const TYPE_PRISE = 'prise';
    const TYPE_DEPOSE = 'depose';
    const TYPE_PRISE_DEPOSE = 'prises et deposes';
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $colis;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $uniqueIdForMobile;

    /**
     * @var DateTime
     * @ORM\Column(type="datetime", length=255, nullable=true)
     */
    private $datetime;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacement")
     */
    private $emplacement;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Statut")
     */
    private $type;

    /**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur")
     */
    private $operateur;

    /**
     * @var MouvementStock|null
	 * @ORM\ManyToOne(targetEntity="App\Entity\MouvementStock")
     * @ORM\JoinColumn(name="mouvement_stock_id", referencedColumnName="id", nullable=true)
     */
    private $mouvementStock;

	/**
	 * @ORM\Column(type="text", nullable=true)
	 */
	private $commentaire;

	/**
	 * @ORM\OneToMany(targetEntity="App\Entity\PieceJointe", mappedBy="mouvementTraca")
	 */
	private $attachements;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $finished;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Reception", inversedBy="mouvementsTraca")
     */
    private $reception;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Arrivage", inversedBy="mouvementsTraca")
     */
    private $arrivage;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ReferenceArticle", inversedBy="mouvementTracas")
     */
    private $referenceArticle;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Article", inversedBy="mouvementTracas")
     */
    private $article;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Colis", mappedBy="lastDrop")
     */
    private $concernedColisLastDrops;

    public function __construct()
    {
        $this->attachements = new ArrayCollection();
        $this->emplacement = new ArrayCollection();
        $this->concernedColisLastDrops = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getColis(): ?string
    {
        return $this->colis;
    }

    public function setColis(?string $colis): self
    {
        $this->colis = $colis;

        return $this;
    }

    public function getUniqueIdForMobile(): ?string
    {
        return $this->uniqueIdForMobile;
    }

    public function setUniqueIdForMobile(?string $uniqueIdForMobile): self
    {
        $this->uniqueIdForMobile = $uniqueIdForMobile;

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

    public function getEmplacement(): ?Emplacement {
        return $this->emplacement;
    }

    public function setEmplacement(?Emplacement $emplacement): self
    {
        $this->emplacement = $emplacement;

        return $this;
    }

    /**
     * @return Collection|PieceJointe[]
     */
    public function getAttachements(): Collection
    {
        return $this->attachements;
    }

    public function addAttachement(PieceJointe $attachement): self
    {
        if (!$this->attachements->contains($attachement)) {
            $this->attachements[] = $attachement;
            $attachement->setMouvementTraca($this);
        }

        return $this;
    }

    public function removeAttachement(PieceJointe $attachement): self
    {
        if ($this->attachements->contains($attachement)) {
            $this->attachements->removeElement($attachement);
            // set the owning side to null (unless already changed)
            if ($attachement->getMouvementTraca() === $this) {
                $attachement->setMouvementTraca(null);
            }
        }

        return $this;
    }

    public function getOperateur(): ?Utilisateur
    {
        return $this->operateur;
    }

    public function setOperateur(?Utilisateur $operateur): self
    {
        $this->operateur = $operateur;

        return $this;
    }

    public function getType(): ?Statut
    {
        return $this->type;
    }

    public function setType(?Statut $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getDatetime(): ?DateTime
    {
        return $this->datetime;
    }

    public function setDatetime(?DateTime $datetime): self
    {
        $this->datetime = $datetime;

        return $this;
    }

    public function isFinished(): ?bool
    {
        return $this->finished;
    }

    public function setFinished(?bool $finished): self {
        $this->finished = $finished;
        return $this;
    }

    public function getMouvementStock(): ?MouvementStock {
        return $this->mouvementStock;
    }

    public function setMouvementStock(?MouvementStock $mouvementStock): self {
        $this->mouvementStock = $mouvementStock;
        return $this;
    }

    public function getFinished(): ?bool
    {
        return $this->finished;
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

    public function getArrivage(): ?Arrivage
    {
        return $this->arrivage;
    }

    public function setArrivage(?Arrivage $arrivage): self
    {
        $this->arrivage = $arrivage;

        return $this;
    }

    public function getReferenceArticle(): ?ReferenceArticle
    {
        return $this->referenceArticle;
    }

    public function setReferenceArticle(?ReferenceArticle $referenceArticle): self
    {
        $this->referenceArticle = $referenceArticle;

        return $this;
    }

    public function getArticle(): ?Article
    {
        return $this->article;
    }

    public function setArticle(?Article $article): self
    {
        $this->article = $article;

        return $this;
    }

    /**
     * @return Collection|Colis[]
     */
    public function getConcernedColisLastDrops(): Collection
    {
        return $this->concernedColisLastDrops;
    }

    public function addConcernedColisLastDrop(Colis $concernedColisLastDrop): self
    {
        if (!$this->concernedColisLastDrops->contains($concernedColisLastDrop)) {
            $this->concernedColisLastDrops[] = $concernedColisLastDrop;
            $concernedColisLastDrop->setLastDrop($this);
        }

        return $this;
    }

    public function removeConcernedColisLastDrop(Colis $concernedColisLastDrop): self
    {
        if ($this->concernedColisLastDrops->contains($concernedColisLastDrop)) {
            $this->concernedColisLastDrops->removeElement($concernedColisLastDrop);
            // set the owning side to null (unless already changed)
            if ($concernedColisLastDrop->getLastDrop() === $this) {
                $concernedColisLastDrop->setLastDrop(null);
            }
        }

        return $this;
    }

}
