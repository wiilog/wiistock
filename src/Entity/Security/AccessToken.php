<?php

namespace App\Entity\Security;

use App\Entity\RequestTemplate\DeliveryRequestTemplateUsageEnum;
use App\Entity\Utilisateur;
use App\Repository\Security\AccessTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AccessTokenRepository::class)]
#[ORM\Index(fields: ["type"], name: "IDX_WIILOG_TYPE")]
class AccessToken extends Token {
    #[ORM\Column(type: Types::STRING, enumType: AccessTokenTypeEnum::class)]
    private ?AccessTokenTypeEnum $type = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, cascade: ['remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $owner = null;

    public function getType(): ?AccessTokenTypeEnum {
        return $this->type;
    }

    public function setType(AccessTokenTypeEnum $type): self {
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
