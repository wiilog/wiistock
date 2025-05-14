<?php

namespace App\Entity;

use App\Entity\Type\Type;
use App\Repository\SessionHistoryRecordRepository;
use App\Service\FormatService;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SessionHistoryRecordRepository::class)]
class SessionHistoryRecord
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
    private ?DateTime $openedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $closedAt = null;


    #[ORM\ManyToOne(inversedBy: 'sessionHistoryRecords')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Type $type = null;

    #[ORM\Column(length: 128, nullable: false)]
    private ?string $sessionId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOpenedAt(): ?DateTime
    {
        return $this->openedAt;
    }

    public function setOpenedAt(DateTime $openedAt): self
    {
        $this->openedAt = $openedAt;

        return $this;
    }

    public function getClosedAt(): ?DateTime
    {
        return $this->closedAt;
    }

    public function setClosedAt(?DateTime $closedAt): self
    {
        $this->closedAt = $closedAt;

        return $this;
    }

    public function getUser(): ?Utilisateur
    {
        return $this->user;
    }

    public function setUser(?Utilisateur $user): self
    {
        if($this->user && $this->user !== $user) {
            $this->user->removeSession($this);
        }
        $this->user = $user;
        $user?->addSession($this);

        return $this;
    }

    public function getType(): ?Type
    {
        return $this->type;
    }

    public function setType(?Type $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(?string $sessionId): self
    {
        $this->sessionId = $sessionId;

        return $this;
    }

    public function serialize(FormatService $formatService): array {
        return [
            'username' => $formatService->user($this->getUser()),
            'email' => $formatService->user($this->getUser(), "", true),
            'type' => $formatService->type($this->getType()),
            'openedAt' => $formatService->datetime($this->getOpenedAt()),
            'closedAt' => $formatService->datetime($this->getClosedAt()),
            'sessionId' => $this->getSessionId(),
        ];
    }
}
