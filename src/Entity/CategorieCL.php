<?php

namespace App\Entity;

use App\Repository\CategorieCLRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CategorieCLRepository::class)]
class CategorieCL {

    const REFERENCE_ARTICLE = 'référence article';
    const ARTICLE = 'article';
    const RECEPTION = 'réception';
    const DEMANDE_LIVRAISON = 'demande livraison';
    const DEMANDE_DISPATCH = 'acheminements';
    const DEMANDE_COLLECTE = 'demande collecte';
    const DEMANDE_HANDLING = 'services';
    const ARRIVAGE = 'arrivage';
    const MVT_TRACA = 'mouvement traca';
    const SENSOR = 'capteur';
    const AUCUNE = 'aucune';
    const DELIVERY_TRANSPORT = CategoryType::DELIVERY_TRANSPORT;
    const COLLECT_TRANSPORT = CategoryType::COLLECT_TRANSPORT;
    const PRODUCTION_REQUEST = 'production';
    const TRACKING_EMERGENCY = CategoryType::TRACKING_EMERGENCY;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $label = null;

    #[ORM\ManyToOne(targetEntity: CategoryType::class, inversedBy: 'categorieCLs')]
    private ?CategoryType $categoryType = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getLabel(): ?string {
        return $this->label;
    }

    public function setLabel(?string $label): self {
        $this->label = $label;

        return $this;
    }

    public function getCategoryType(): ?CategoryType {
        return $this->categoryType;
    }

    public function setCategoryType(?CategoryType $categoryType): self {
        $this->categoryType = $categoryType;

        return $this;
    }

}
