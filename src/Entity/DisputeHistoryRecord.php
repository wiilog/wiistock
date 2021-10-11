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
     * @ORM\ManyToOne(targetEntity="App\Entity\Litige", inversedBy="disputeHistory")
     * @ORM\JoinColumn(name="dispute_id", referencedColumnName="id", onDelete="CASCADE", nullable=false)
     */
    private ?Litige $dispute = null;

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

    public function getDispute(): ?Litige {
        return $this->dispute;
    }

    public function setDispute(?Litige $dispute): self {
        if($this->dispute && $this->dispute !== $dispute) {
            $this->dispute->removeDisputeHistoryRecord($this);
        }
        $this->dispute = $dispute;
        if($dispute) {
            $dispute->addDisputeHistoryRecord($this);
        }

        return $this;
    }
}
