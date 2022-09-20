<?php

namespace App\Entity;

use App\Repository\ExportRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExportRepository::class)]
class Export
{
    const STATUS_FINISHED = 'terminé';
    const STATUS_PLANIFIED = 'planifiée';
    const STATUS_ERROR = 'erreur';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $entity = null;

    #[ORM\ManyToOne(targetEntity: Statut::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Statut $status = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $user = null;

    #[ORM\Column]
    private ?bool $forced = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\ManyToOne(targetEntity: Type::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Type $type = null;

    #[ORM\Column(nullable: true)]
    private array $columnToExport = [];

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $beginAt = null;

    #[ORM\Column(length: 255)]
    private ?string $frequency = null;

    #[ORM\Column(length: 255)]
    private ?string $exportDestination = null;

    #[ORM\Column(nullable: true)]
    private array $parameters = [];

    #[ORM\ManyToMany(targetEntity: Utilisateur::class)]
    private Collection $userEmails;

    #[ORM\Column(nullable: true)]
    private array $freeEmails = [];

    #[ORM\Column(length: 255)]
    private ?string $period = null;

    #[ORM\Column(length: 255)]
    private ?string $periodInterval = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $endedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $nextExecution = null;

    public function __construct()
    {
        $this->userEmails = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntity(): ?string
    {
        return $this->entity;
    }

    public function setEntity(string $entity): self
    {
        $this->entity = $entity;

        return $this;
    }

    public function getStatus(): ?Statut
    {
        return $this->status;
    }

    public function setStatus(?Statut $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getUser(): ?Utilisateur
    {
        return $this->user;
    }

    public function setUser(?Utilisateur $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function isForced(): ?bool
    {
        return $this->forced;
    }

    public function setForced(bool $forced): self
    {
        $this->forced = $forced;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;

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

    public function getColumnToExport(): array
    {
        return $this->columnToExport;
    }

    public function setColumnToExport(?array $columnToExport): self
    {
        $this->columnToExport = $columnToExport;

        return $this;
    }

    public function getBeginAt(): ?\DateTimeInterface
    {
        return $this->beginAt;
    }

    public function setBeginAt(?\DateTimeInterface $beginAt): self
    {
        $this->beginAt = $beginAt;

        return $this;
    }

    public function getFrequency(): ?string
    {
        return $this->frequency;
    }

    public function setFrequency(string $frequency): self
    {
        $this->frequency = $frequency;

        return $this;
    }

    public function getExportDestination(): ?string
    {
        return $this->exportDestination;
    }

    public function setExportDestination(string $exportDestination): self
    {
        $this->exportDestination = $exportDestination;

        return $this;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function setParameters(?array $parameters): self
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * @return Collection<int, Utilisateur>
     */
    public function getUserEmails(): Collection
    {
        return $this->userEmails;
    }

    public function addUserEmail(Utilisateur $userEmail): self
    {
        if (!$this->userEmails->contains($userEmail)) {
            $this->userEmails[] = $userEmail;
        }

        return $this;
    }

    public function removeUserEmail(Utilisateur $userEmail): self
    {
        $this->userEmails->removeElement($userEmail);

        return $this;
    }

    public function getFreeEmails(): array
    {
        return $this->freeEmails;
    }

    public function setFreeEmails(?array $freeEmails): self
    {
        $this->freeEmails = $freeEmails;

        return $this;
    }

    public function getPeriod(): ?string
    {
        return $this->period;
    }

    public function setPeriod(string $period): self
    {
        $this->period = $period;

        return $this;
    }

    public function getPeriodInterval(): ?string
    {
        return $this->periodInterval;
    }

    public function setPeriodInterval(string $periodInterval): self
    {
        $this->periodInterval = $periodInterval;

        return $this;
    }

    public function getEndedAt(): ?\DateTimeInterface
    {
        return $this->endedAt;
    }

    public function setEndedAt(?\DateTimeInterface $endedAt): self
    {
        $this->endedAt = $endedAt;

        return $this;
    }

    public function getNextExecution(): ?\DateTimeInterface
    {
        return $this->nextExecution;
    }

    public function setNextExecution(?\DateTimeInterface $nextExecution): self
    {
        $this->nextExecution = $nextExecution;

        return $this;
    }
}
