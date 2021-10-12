<?php

namespace App\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\DisputeHistoryRecordRepository;

/**
 * @ORM\Entity(repositoryClass=DisputeHistoryRecordRepository::class)
 */
class DisputeHistoryRecord
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="datetime")
     */
    private ?DateTime $date = null;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $comment = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur", inversedBy="disputeHistory")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Utilisateur $user = null;

    /**
     * @ORM\ManyToOne(targetEntity=Dispute::class, inversedBy="disputeHistory")
     * @ORM\JoinColumn(onDelete="CASCADE", nullable=false)
     */
    private ?Dispute $dispute = null;

    /**
     * @ORM\ManyToOne(targetEntity=Statut::class, inversedBy="disputeHistoryRecords")
     * @ORM\JoinColumn(onDelete="CASCADE", nullable=false)
     */
    private ?Statut $status = null;

    /**
     * @ORM\ManyToOne(targetEntity=Type::class, inversedBy="disputeHistory")
     * @ORM\JoinColumn(onDelete="CASCADE", nullable=false)
     */
    private ?Type $type = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?DateTime
    {
        return $this->date;
    }

    public function setDate(DateTime $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
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

    public function getStatus(): ?Statut {
        return $this->status;
    }

    public function setStatus(?Statut $status): self {
        if($this->status && $this->status !== $status) {
            $this->status->removeDisputeHistoryRecord($this);
        }
        $this->status = $status;
        if($status) {
            $status->addDisputeHistoryRecord($this);
        }

        return $this;
    }

    public function getType(): ?Type {
        return $this->type;
    }

    public function setType(?Type $type): self {
        if($this->type && $this->type !== $type) {
            $this->type->removeDisputeHistoryRecord($this);
        }
        $this->type = $type;
        if($type) {
            $type->addDisputeHistoryRecord($this);
        }

        return $this;
    }
}
