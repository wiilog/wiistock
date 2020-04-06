<?php

namespace App\Entity;

use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\MouvementStockRepository")
 */
class MouvementStock
{
	const TYPE_ENTREE = 'entrée';
	const TYPE_SORTIE = 'sortie';
	const TYPE_TRANSFERT = 'transfert';
	const TYPE_INVENTAIRE_ENTREE = 'entrée inventaire';
	const TYPE_INVENTAIRE_SORTIE = 'sortie inventaire';
	/**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $date;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacement")
     */
    private $emplacementFrom;


	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Emplacement")
	 */
	private $emplacementTo;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="mouvements")
     */
    private $user;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Article", inversedBy="mouvements")
     */
    private $article;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\ReferenceArticle", inversedBy="mouvements")
	 */
    private $refArticle;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $type;

	/**
	 * @ORM\Column(type="integer")
	 */
    private $quantity;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Livraison", inversedBy="mouvements")
     * @ORM\JoinColumn(name="livraison_order_id", referencedColumnName="id", onDelete="CASCADE")
	 */
    private $livraisonOrder;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\OrdreCollecte", inversedBy="mouvements")
     * @ORM\JoinColumn(name="collecte_order_id", referencedColumnName="id", onDelete="CASCADE")
	 */
	private $collecteOrder;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Preparation", inversedBy="mouvements")
     * @ORM\JoinColumn(name="preparation_order_id", referencedColumnName="id", onDelete="CASCADE")
	 */
	private $preparationOrder;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Import", inversedBy="mouvements")
     * @ORM\JoinColumn(name="import_id", referencedColumnName="id", nullable=true, onDelete="CASCADE")
	 */
	private $import;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Reception", inversedBy="mouvements")
	 * @ORM\JoinColumn(name="reception_order_id", referencedColumnName="id", nullable=true, onDelete="CASCADE")
	 */
	private $receptionOrder;

	/**
	 * @ORM\Column(type="text", nullable=true)
	 */
	private $comment;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getEmplacementFrom(): ?Emplacement
    {
        return $this->emplacementFrom;
    }

    public function setEmplacementFrom(?Emplacement $emplacementFrom): self
    {
        $this->emplacementFrom = $emplacementFrom;

        return $this;
    }

    public function getUser(): ?Utilisateur
    {
        return $this->user;
    }

    public function setUser(?Utilisateur $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;

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

    public function getRefArticle(): ?ReferenceArticle
    {
        return $this->refArticle;
    }

    public function setRefArticle(?ReferenceArticle $refArticle): self
    {
        $this->refArticle = $refArticle;

        return $this;
    }

    public function getLivraisonOrder(): ?Livraison
    {
        return $this->livraisonOrder;
    }

    public function setLivraisonOrder(?Livraison $livraisonOrder): self
    {
        $this->livraisonOrder = $livraisonOrder;

        return $this;
    }

    public function getCollecteOrder(): ?OrdreCollecte
    {
        return $this->collecteOrder;
    }

    public function setCollecteOrder(?OrdreCollecte $collecteOrder): self
    {
        $this->collecteOrder = $collecteOrder;

        return $this;
    }

    public function getPreparationOrder(): ?Preparation
    {
        return $this->preparationOrder;
    }

    public function setPreparationOrder(?Preparation $preparationOrder): self
    {
        $this->preparationOrder = $preparationOrder;

        return $this;
    }

    public function getImport(): ?Import
    {
        return $this->import;
    }

    public function setImport(?Import $import): self
    {
        $this->import = $import;

        return $this;
    }

    public function getEmplacementTo(): ?Emplacement
    {
        return $this->emplacementTo;
    }

    public function setEmplacementTo(?Emplacement $emplacementTo): self
    {
        $this->emplacementTo = $emplacementTo;

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

    public function getReceptionOrder(): ?Reception
    {
        return $this->receptionOrder;
    }

    public function setReceptionOrder(?Reception $receptionOrder): self
    {
        $this->receptionOrder = $receptionOrder;

        return $this;
    }

}
