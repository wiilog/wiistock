<?php

namespace App\Entity;

use App\Repository\NotificationTemplateRepository;
use App\Service\VariableService;
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

    public const DICTIONARIES = [
        self::PREPARATION => VariableService::DELIVERY_DICTIONARY,
        self::DELIVERY => VariableService::DELIVERY_DICTIONARY,
        self::COLLECT => VariableService::COLLECT_DICTIONARY,
        self::TRANSFER => VariableService::TRANSFER_DICTIONARY,
        self::DISPATCH => VariableService::DISPATCH_DICTIONARY,
        self::HANDLING => VariableService::HANDLING_DICTIONARY,
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
