<?php

namespace App\Entity\Tracking;

use App\Repository\Tracking\PackSplitRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PackSplitRepository::class)]
class PackSplit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
    private ?DateTime $splittingAt;

    #[ORM\ManyToOne(targetEntity: Pack::class, inversedBy: 'splitTargets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Pack $from = null;

    #[ORM\OneToOne(inversedBy: 'splitFrom', targetEntity: Pack::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Pack $target = null;

    public function __construct() {
        $this->splittingAt = new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSplittingAt(): DateTime
    {
        return $this->splittingAt;
    }

    public function setSplittingAt(DateTime $splittingAt): static
    {
        $this->splittingAt = $splittingAt;

        return $this;
    }

    public function getFrom(): ?Pack
    {
        return $this->from;
    }

    public function setFrom(?Pack $from): self
    {
        if($this->from && $this->from !== $from) {
            $this->from->removeSplitTarget($this);
        }
        $this->from = $from;
        $from?->addSplitTarget($this);

        return $this;
    }

    public function getTarget(): ?Pack
    {
        return $this->target;
    }

    public function setTarget(?Pack $target): self
    {
        if($this->target && $this->target->getSplitFrom() !== $this) {
            $oldTarget = $this->getTarget();
            $this->target = null;
            $oldTarget->setSplitFrom(null);
        }

        $this->target = $target;

        if($this->target && $this->target->getSplitFrom() !== $this) {
            $this->target->setSplitFrom($this);
        }
        return $this;
    }
}
