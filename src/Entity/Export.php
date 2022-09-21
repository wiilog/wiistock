<?php

namespace App\Entity;

use App\Repository\ExportRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExportRepository::class)]
class Export {

    const STATUS_FINISHED = "terminé";
    const STATUS_PLANIFIED = "planifiée";
    const STATUS_ERROR = "erreur";

    const ENTITY_REFERENCE = "reference";
    const ENTITY_ARTICLE = "article";
    const ENTITY_DELIVERY_ROUND = "tournee";
    const ENTITY_ARRIVAL = "arrivage";

    const ENTITY_LABELS = [
        self::ENTITY_REFERENCE => "Références",
        self::ENTITY_ARTICLE => "Articles",
        self::ENTITY_DELIVERY_ROUND => "Tournées",
        self::ENTITY_ARRIVAL => "Arrivages",
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $entity = null;

    #[ORM\ManyToOne(targetEntity: Statut::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Statut $status = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $creator = null;

    #[ORM\Column(type: "boolean")]
    private ?bool $forced = null;

    #[ORM\Column(type: "datetime")]
    private ?DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(targetEntity: Type::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Type $type = null;

    #[ORM\Column(type: "json", nullable: true)]
    private array $columnToExport = [];

    #[ORM\Column(type: "string", length: 255)]
    private ?string $frequency = null;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $exportDestination = null;

    #[ORM\Column(type: "json", nullable: true)]
    private array $ftpParameters = [];

    #[ORM\Column(type: "json", nullable: true)]
    private array $recipientEmails = [];

    #[ORM\ManyToMany(targetEntity: Utilisateur::class)]
    private Collection $recipientUsers;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $period = null;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $periodInterval = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?DateTimeInterface $beganAt = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?DateTimeInterface $endedAt = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?DateTimeInterface $nextExecution = null;

    public function __construct() {
        $this->recipientUsers = new ArrayCollection();
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

    public function getCreator(): ?Utilisateur
    {
        return $this->creator;
    }

    public function setCreator(?Utilisateur $creator): self
    {
        $this->creator = $creator;

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

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

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

    public function getBeganAt(): ?DateTimeInterface
    {
        return $this->beganAt;
    }

    public function setBeganAt(?DateTimeInterface $beganAt): self
    {
        $this->beganAt = $beganAt;

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

    public function getFtpParameters(): array
    {
        return $this->ftpParameters;
    }

    public function setFtpParameters(?array $ftpParameters): self
    {
        $this->ftpParameters = $ftpParameters;

        return $this;
    }

    public function getRecipientEmails(): array
    {
        return $this->recipientEmails;
    }

    public function setRecipientEmails(?array $recipientEmails): self
    {
        $this->recipientEmails = $recipientEmails;

        return $this;
    }

    /**
     * @return Collection<int, Utilisateur>
     */
    public function getRecipientUsers(): Collection
    {
        return $this->recipientUsers;
    }

    public function addUserEmail(Utilisateur $userEmail): self
    {
        if (!$this->recipientUsers->contains($userEmail)) {
            $this->recipientUsers[] = $userEmail;
        }

        return $this;
    }

    public function removeUserEmail(Utilisateur $userEmail): self
    {
        $this->recipientUsers->removeElement($userEmail);

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

    public function getEndedAt(): ?DateTimeInterface
    {
        return $this->endedAt;
    }

    public function setEndedAt(?DateTimeInterface $endedAt): self
    {
        $this->endedAt = $endedAt;

        return $this;
    }

    public function getNextExecution(): ?DateTimeInterface
    {
        return $this->nextExecution;
    }

    public function setNextExecution(?DateTimeInterface $nextExecution): self
    {
        $this->nextExecution = $nextExecution;

        return $this;
    }
}
