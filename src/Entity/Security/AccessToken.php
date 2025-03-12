<?php

namespace App\Entity\Security;

use App\Entity\RequestTemplate\DeliveryRequestTemplateUsageEnum;
use App\Entity\Utilisateur;
use App\Repository\Security\AccessTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AccessTokenRepository::class)]
class AccessToken extends Token {
    #[ORM\Column(type: Types::STRING, enumType: DeliveryRequestTemplateUsageEnum::class)]
    private ?DeliveryRequestTemplateUsageEnum $type = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $owner = null;

    public function getType(): ?DeliveryRequestTemplateUsageEnum {
        return $this->type;
    }

    public function setType(DeliveryRequestTemplateUsageEnum $type): self {
        $this->type = $type;

        return $this;
    }

    public function getOwner(): ?Utilisateur {
        return $this->owner;
    }

    public function setOwner(?Utilisateur $owner): self {
        $this->owner = $owner;

        return $this;
    }
}
