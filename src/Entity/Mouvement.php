<?php

namespace App\Entity;

use Amm\Entity\ReferenceArticle;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\MouvementRepository")
 */
class Mouvement
{
	const TYPE_ENTREE = 'entrée';
	const TYPE_SORTIE = 'sortie';
	const TYPE_TRANSFERT = 'transfert';
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

	/**
	 * @ORM\Column(type="datetime", nullable=true)
	 */
    private $expectedDate;

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
	 */
    private $livraisonOrder;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Collecte", inversedBy="mouvements")
	 */
	private $collecteOrder;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Preparation", inversedBy="mouvements")
	 */
	private $preparationOrder;


    public function __construct()
    {
        $this->article = new ArrayCollection();
        $this->refArticle = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getExpectedDate(): ?\DateTimeInterface
    {
        return $this->expectedDate;
    }

    public function setExpectedDate(?\DateTimeInterface $expectedDate): self
    {
        $this->expectedDate = $expectedDate;

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

    public function getCollecteOrder(): ?Collecte
    {
        return $this->collecteOrder;
    }

    public function setCollecteOrder(?Collecte $collecteOrder): self
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

    public function getEmplacementTo(): ?Emplacement
    {
        return $this->emplacementTo;
    }

    public function setEmplacementTo(?Emplacement $emplacementTo): self
    {
        $this->emplacementTo = $emplacementTo;

        return $this;
    }

}
