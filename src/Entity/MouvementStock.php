<?php

namespace App\Entity;

use App\Entity\DeliveryRequest\Demande;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\ScheduledTask\Import;
use App\Entity\ShippingRequest\ShippingRequest;
use App\Repository\MouvementStockRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Traits\CleanedCommentTrait;

#[ORM\Entity(repositoryClass: MouvementStockRepository::class)]
class MouvementStock {

    use CleanedCommentTrait;

    const TYPE_ENTREE = 'entrée';
    const TYPE_SORTIE = 'sortie';
    const TYPE_TRANSFER = 'transfert';
    const TYPE_INVENTAIRE_ENTREE = 'entrée inventaire';
    const TYPE_INVENTAIRE_SORTIE = 'sortie inventaire';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $date = null;

    #[ORM\ManyToOne(targetEntity: Emplacement::class)]
    private ?Emplacement $emplacementFrom = null;

    #[ORM\ManyToOne(targetEntity: Emplacement::class)]
    private ?Emplacement $emplacementTo = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'mouvements')]
    private ?Utilisateur $user = null;

    #[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'mouvements')]
    private ?Article $article = null;

    #[ORM\ManyToOne(targetEntity: ReferenceArticle::class, inversedBy: 'mouvements')]
    private ?ReferenceArticle $refArticle = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $quantity = null;

    #[ORM\ManyToOne(targetEntity: Livraison::class, inversedBy: 'mouvements')]
    #[ORM\JoinColumn(name: 'livraison_order_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Livraison $livraisonOrder = null;

    #[ORM\ManyToOne(targetEntity: Demande::class, inversedBy: 'stockMovements')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Demande $deliveryRequest = null;

    #[ORM\ManyToOne(targetEntity: OrdreCollecte::class, inversedBy: 'mouvements')]
    #[ORM\JoinColumn(name: 'collecte_order_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?OrdreCollecte $collecteOrder = null;

    #[ORM\ManyToOne(targetEntity: Preparation::class, inversedBy: 'mouvements')]
    #[ORM\JoinColumn(name: 'preparation_order_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Preparation $preparationOrder = null;

    #[ORM\ManyToOne(targetEntity: ShippingRequest::class, inversedBy: 'stockMovements')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?ShippingRequest $shippingRequest = null;

    #[ORM\ManyToOne(targetEntity: Import::class, inversedBy: 'mouvements')]
    #[ORM\JoinColumn(name: 'import_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Import $import = null;

    #[ORM\ManyToOne(targetEntity: Reception::class, inversedBy: 'mouvements')]
    #[ORM\JoinColumn(name: 'reception_order_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Reception $receptionOrder = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\ManyToOne(targetEntity: TransferOrder::class, inversedBy: 'stockMovements')]
    private ?TransferOrder $transferOrder = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3, nullable: true)]
    private ?string $unitPrice = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getDate(): ?DateTimeInterface {
        return $this->date;
    }

    public function setDate(?DateTimeInterface $date): self {
        $this->date = $date;

        return $this;
    }

    public function getEmplacementFrom(): ?Emplacement {
        return $this->emplacementFrom;
    }

    public function setEmplacementFrom(?Emplacement $emplacementFrom): self {
        $this->emplacementFrom = $emplacementFrom;

        return $this;
    }

    public function getUser(): ?Utilisateur {
        return $this->user;
    }

    public function setUser(?Utilisateur $user): self {
        $this->user = $user;

        return $this;
    }

    public function getType(): ?string {
        return $this->type;
    }

    public function setType(?string $type): self {
        $this->type = $type;

        return $this;
    }

    public function getQuantity(): ?int {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self {
        $this->quantity = $quantity;

        return $this;
    }

    public function getArticle(): ?Article {
        return $this->article;
    }

    public function setArticle(?Article $article): self {
        $this->article = $article;

        return $this;
    }

    public function getRefArticle(): ?ReferenceArticle {
        return $this->refArticle;
    }

    public function setRefArticle(?ReferenceArticle $refArticle): self {
        $this->refArticle = $refArticle;

        return $this;
    }

    public function getLivraisonOrder(): ?Livraison {
        return $this->livraisonOrder;
    }

    public function setLivraisonOrder(?Livraison $livraisonOrder): self {
        $this->livraisonOrder = $livraisonOrder;

        return $this;
    }

    public function getDeliveryRequest(): ?Demande {
        return $this->deliveryRequest;
    }

    public function setDeliveryRequest(?Demande $deliveryRequest): self {
        $this->deliveryRequest = $deliveryRequest;

        return $this;
    }

    public function getCollecteOrder(): ?OrdreCollecte {
        return $this->collecteOrder;
    }

    public function setCollecteOrder(?OrdreCollecte $collecteOrder): self {
        $this->collecteOrder = $collecteOrder;

        return $this;
    }

    public function getPreparationOrder(): ?Preparation {
        return $this->preparationOrder;
    }

    public function setPreparationOrder(?Preparation $preparationOrder): self {
        $oldPreparationOrder = $this->preparationOrder;
        $this->preparationOrder = $preparationOrder;

        if(isset($oldPreparationOrder) && $oldPreparationOrder !== $preparationOrder) {
            $oldPreparationOrder->removeMouvement($this);
        }

        if(isset($this->preparationOrder)) {
            $this->preparationOrder->addMouvement($this);
        }

        return $this;
    }

    public function getImport(): ?Import {
        return $this->import;
    }

    public function setImport(?Import $import): self {
        $this->import = $import;

        return $this;
    }

    public function getEmplacementTo(): ?Emplacement {
        return $this->emplacementTo;
    }

    public function setEmplacementTo(?Emplacement $emplacementTo): self {
        $this->emplacementTo = $emplacementTo;

        return $this;
    }

    public function getComment(): ?string {
        return $this->comment;
    }

    public function setComment(?string $comment): self {
        $this->comment = $comment;
        $this->setCleanedComment($comment);
        return $this;
    }

    public function getReceptionOrder(): ?Reception {
        return $this->receptionOrder;
    }

    public function setReceptionOrder(?Reception $receptionOrder): self {
        $this->receptionOrder = $receptionOrder;

        return $this;
    }

    public function getTransferOrder(): ?TransferOrder {
        return $this->transferOrder;
    }

    public function setTransferOrder(?TransferOrder $transferOrder): self {
        $this->transferOrder = $transferOrder;

        return $this;
    }
    public function getShippingRequest(): ?ShippingRequest {
        return $this->shippingRequest;
    }

    public function setShippingRequest(?ShippingRequest $shippingRequest): self {
        $this->shippingRequest = $shippingRequest;

        return $this;
    }

    public function getUnitPrice(): ?float {
        return isset($this->unitPrice)
            ? ((float) $this->unitPrice)
            : null;
    }

    public function setUnitPrice(?float $unitPrice): self {
        $this->unitPrice = isset($unitPrice)
            ? ((string) $unitPrice)
            : null;

        return $this;
    }

}
