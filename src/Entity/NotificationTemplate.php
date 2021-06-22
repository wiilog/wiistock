<?php

namespace App\Entity;

use App\Repository\NotificationTemplateRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=NotificationTemplateRepository::class)
 */
class NotificationTemplate
{

    const NOTIFICATIONS_TYPES = [
        CategoryType::DEMANDE_LIVRAISON,
        CategoryType::DEMANDE_COLLECTE,
        CategoryType::TRANSFER_REQUEST,
        CategoryType::DEMANDE_DISPATCH,
        CategoryType::DEMANDE_HANDLING,
    ];

    const NOTIFICATIONS_EMERGENCIES = [
        CategoryType::DEMANDE_DISPATCH,
        CategoryType::DEMANDE_HANDLING,
    ];

    public const PREPARATION = "preparation";
    public const DELIVERY = "delivery";
    public const COLLECT = "collect";
    public const TRANSFER = "transfer";
    public const DISPATCH = "dispatch";
    public const HANDLING = "service";

    public const READABLE_TYPES = [
        self::PREPARATION => "Ordre de préparation",
        self::DELIVERY => "Ordre de livraison",
        self::COLLECT => "Ordre de collecte",
        self::TRANSFER => "Ordre de transfert",
        self::DISPATCH => "Demande d'acheminement",
        self::HANDLING => "Demande de service",
    ];

    public const CATEGORIES = [
        self::PREPARATION => CategoryType::DEMANDE_LIVRAISON,
        self::DELIVERY => CategoryType::DEMANDE_LIVRAISON,
        self::COLLECT => CategoryType::DEMANDE_COLLECTE,
        self::TRANSFER => CategoryType::TRANSFER_REQUEST,
        self::DISPATCH => CategoryType::DEMANDE_DISPATCH,
        self::HANDLING => CategoryType::DEMANDE_HANDLING,
    ];

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id;

    /**
     * @ORM\Column(type="string")
     */
    private ?string $type;

    /**
     * @ORM\Column(type="text")
     */
    private ?string $content;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }
}
