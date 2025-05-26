<?php

namespace App\Entity;

use App\Entity\Type\CategoryType;
use App\Repository\NotificationTemplateRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationTemplateRepository::class)]
class NotificationTemplate {

    public const NOTIFICATIONS_TYPES = [
        CategoryType::DEMANDE_LIVRAISON,
        CategoryType::DEMANDE_COLLECTE,
        CategoryType::TRANSFER_REQUEST,
        CategoryType::DEMANDE_DISPATCH,
        CategoryType::DEMANDE_HANDLING,
    ];
    public const NOTIFICATIONS_EMERGENCIES = [
        CategoryType::DEMANDE_DISPATCH,
        CategoryType::DEMANDE_HANDLING,
    ];
    public const PREPARATION = "preparation";
    public const DELIVERY = "delivery";
    public const COLLECT = "collect";
    public const TRANSFER = "transfer";
    public const DISPATCH = "dispatch";
    public const HANDLING = "service";

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string')]
    private ?string $type = null;

    #[ORM\Column(type: 'text')]
    private ?string $content = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getType(): ?string {
        return $this->type;
    }

    public function setType(string $type): self {
        $this->type = $type;

        return $this;
    }

    public function getContent(): ?string {
        return $this->content;
    }

    public function setContent(string $content): self {
        $this->content = $content;

        return $this;
    }

}
