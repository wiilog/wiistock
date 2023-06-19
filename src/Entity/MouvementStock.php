<?php

namespace App\Entity;

use App\Entity\DeliveryRequest\Demande;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\ShippingRequest\ShippingRequest;
use App\Repository\MouvementStockRepository;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MouvementStockRepository::class)]
class MouvementStock {

    const TYPE_ENTREE = 'entrée';
    const TYPE_SORTIE = 'sortie';
    const TYPE_TRANSFER = 'transfert';
    const TYPE_INVENTAIRE_ENTREE = 'entrée inventaire';
    const TYPE_INVENTAIRE_SORTIE = 'sortie inventaire';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $date;

    #[ORM\ManyToOne(targetEntity: Emplacement::class)]
    private $emplacementFrom;

    #[ORM\ManyToOne(targetEntity: Emplacement::class)]
    private $emplacementTo;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'mouvements')]
    private $user;

    #[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'mouvements')]
    private $article;

    #[ORM\ManyToOne(targetEntity: ReferenceArticle::class, inversedBy: 'mouvements')]
    private $refArticle;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $type;

    #[ORM\Column(type: 'integer')]
    private $quantity;

    #[ORM\ManyToOne(targetEntity: Livraison::class, inversedBy: 'mouvements')]
    #[ORM\JoinColumn(name: 'livraison_order_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private $livraisonOrder;

    #[ORM\ManyToOne(targetEntity: Demande::class, inversedBy: 'stockMovements')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Demande $deliveryRequest = null;

    #[ORM\ManyToOne(targetEntity: OrdreCollecte::class, inversedBy: 'mouvements')]
    #[ORM\JoinColumn(name: 'collecte_order_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private $collecteOrder;

    #[ORM\ManyToOne(targetEntity: Preparation::class, inversedBy: 'mouvements')]
    #[ORM\JoinColumn(name: 'preparation_order_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private $preparationOrder;

    #[ORM\ManyToOne(targetEntity: ShippingRequest::class, inversedBy: 'stockMovements')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?ShippingRequest $shippingRequest = null;

    #[ORM\ManyToOne(targetEntity: Import::class, inversedBy: 'mouvements')]
    #[ORM\JoinColumn(name: 'import_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private $import;

    #[ORM\ManyToOne(targetEntity: Reception::class, inversedBy: 'mouvements')]
    #[ORM\JoinColumn(name: 'reception_order_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private $receptionOrder;

    #[ORM\Column(type: 'text', nullable: true)]
    private $comment;

    #[ORM\ManyToOne(targetEntity: TransferOrder::class, inversedBy: 'stockMovements')]
    private $transferOrder;

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

}
