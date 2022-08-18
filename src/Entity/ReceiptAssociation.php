<?php

namespace App\Entity;

use App\Helper\FormatHelper;
use App\Repository\ReceiptAssociationRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReceiptAssociationRepository::class)]
class ReceiptAssociation {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $creationDate = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'receptionsTraca')]
    private ?Utilisateur $user = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $packCode = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $receptionNumber = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getCreationDate(): ?\DateTimeInterface {
        return $this->creationDate;
    }

    public function setCreationDate(?\DateTimeInterface $creationDate): self {
        $this->creationDate = $creationDate;

        return $this;
    }

    public function getUser(): ?Utilisateur {
        return $this->user;
    }

    public function setUser(?Utilisateur $user): self {
        $this->user = $user;

        return $this;
    }

    public function serialize(): array {
        return [
            'creationDate' => FormatHelper::datetime($this->getCreationDate()),
            'packCode' => $this->getPackCode() ?? '',
            'receptionNumber' => $this->getReceptionNumber() ?? '',
            'user' => FormatHelper::user($this->getUser()),
        ];
    }

    public function getReceptionNumber(): ?string {
        return $this->receptionNumber;
    }

    public function setReceptionNumber(?string $receptionNumber): self {
        $this->receptionNumber = $receptionNumber;

        return $this;
    }

    public function getPackCode(): ?string {
        return $this->packCode;
    }

    public function setPackCode(?string $packCode): self {
        $this->packCode = $packCode;

        return $this;
    }

}
