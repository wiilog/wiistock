<?php

namespace App\Entity;

use App\Helper\FormatHelper;
use App\Repository\AlertRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=AlertRepository::class)
 */
class Alert {

    const TYPE_LABELS = [
        self::SECURITY => "Seuil de sécurité",
        self::WARNING => "Seuil d'alerte",
        self::EXPIRY => "Péremption",
    ];

    const TYPE_LABELS_IDS = [
        'expiration' => self::EXPIRY,
        'alert' => self::WARNING,
        'security' => self::SECURITY,
    ];

    const SECURITY = 1;
    const WARNING = 2;
    const EXPIRY = 3;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=ReferenceArticle::class, inversedBy="alerts")
     */
    private $reference;

    /**
     * @ORM\ManyToOne(targetEntity=Article::class, inversedBy="alerts")
     */
    private $article;

    /**
     * @ORM\Column(type="integer")
     */
    private $type;

    /**
     * @ORM\Column(type="datetime")
     */
    private $date;

    public function getId(): ?int {
        return $this->id;
    }

    public function getReference(): ?ReferenceArticle {
        return $this->reference;
    }

    public function setReference(?ReferenceArticle $reference): self {
        $this->reference = $reference;
        return $this;
    }

    public function getArticle(): ?Article {
        return $this->article;
    }

    public function setArticle(?Article $article): self {
        $this->article = $article;
        return $this;
    }

    public function getType(): ?int {
        return $this->type;
    }

    public function setType(int $type): self {
        $this->type = $type;
        return $this;
    }

    public function getDate(): ?\DateTimeInterface {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self {
        $this->date = $date;
        return $this;
    }

    public function serialize(): array
    {
        $type = $this->getType();
        $dateAlerte = FormatHelper::date($this->getDate());

        if ($this->getReference()) {
            $referenceArticle = $this->getReference();
            $article = null;
        }
        else if ($this->getArticle()) {
            $article = $this->getArticle();
            $articleFournisseur = $article->getArticleFournisseur();
            $referenceArticle = $articleFournisseur
                ? $articleFournisseur->getReferenceArticle()
                : null;
        }
        else {
            $referenceArticle = null;
            $article = null;
        }

        return [
            'type' => self::TYPE_LABELS[$type],
            'date' => $dateAlerte,
            'label' => $referenceArticle ? $referenceArticle->getLibelle() : '',
            'reference' => $referenceArticle ? $referenceArticle->getReference() : '',
            'barcode' => ($article
                ? $article->getBarCode()
                : ($referenceArticle
                    ? $referenceArticle->getBarCode()
                    : '')),
            'availableQuantity' => ($this->getReference()
                ? $this->getReference()->getQuantiteDisponible()
                : ($this->getArticle()
                    ? $this->getArticle()->getQuantite()
                    : '' )),
            'typeQuantity' => $referenceArticle->getTypeQuantite()
                ? $referenceArticle->getTypeQuantite()
                : '',
            'limitWarning' => $this->getReference()
                ? $this->getReference()->getLimitWarning()
                : '',
            'limitSecurity' => $this->getReference()
                ? $this->getReference()->getLimitSecurity()
                : '',
            'expiryDate' => $this->getArticle()
                ? FormatHelper::date($this->getArticle()->getExpiryDate())
                : '',
            'managers' => $this->getReference()
                ? FormatHelper::users($this->getReference()->getManagers()->toArray())
                : '',
        ];
    }
}
