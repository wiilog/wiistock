<?php

namespace App\Entity;

use App\Repository\DisputeHistoryRecordRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DisputeHistoryRecordRepository::class)]
class DisputeHistoryRecord {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime')]
    private ?DateTime $date = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'disputeHistoryRecords')]
    private ?Utilisateur $user = null;

    #[ORM\ManyToOne(targetEntity: Dispute::class, inversedBy: 'disputeHistory')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Dispute $dispute = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $statusLabel = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $typeLabel = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getDate(): ?DateTime {
        return $this->date;
    }

    public function setDate(DateTime $date): self {
        $this->date = $date;

        return $this;
    }

    public function getComment(): ?string {
        return $this->comment;
    }

    public function setComment(?string $comment): self {
        $this->comment = $comment;

        return $this;
    }

    public function getUser(): ?Utilisateur {
        return $this->user;
    }

    public function setUser(?Utilisateur $user): self {
        if($this->user && $this->user !== $user) {
            $this->user->removeDisputeHistoryRecord($this);
        }
        $this->user = $user;
        if($user) {
            $user->addDisputeHistoryRecord($this);
        }

        return $this;
    }

    public function getDispute(): ?Dispute {
        return $this->dispute;
    }

    public function setDispute(?Dispute $dispute): self {
        if($this->dispute && $this->dispute !== $dispute) {
            $this->dispute->removeDisputeHistoryRecord($this);
        }
        $this->dispute = $dispute;
        if($dispute) {
            $dispute->addDisputeHistoryRecord($this);
        }

        return $this;
    }

    public function getStatusLabel(): ?string {
        return $this->statusLabel;
    }

    public function setStatusLabel(?string $statusLabel): self {
        $this->statusLabel = $statusLabel;
        return $this;
    }

    public function getTypeLabel(): ?string {
        return $this->typeLabel;
    }

    public function setTypeLabel(?string $typeLabel): self {
        $this->typeLabel = $typeLabel;
        return $this;
    }

}
