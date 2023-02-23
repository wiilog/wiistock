<?php

namespace App\Entity;

use App\Repository\ExportRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExportRepository::class)]
class Export {

    const STATUS_FINISHED = "terminé";
    const STATUS_CANCELLED = "annulé";
    const STATUS_SCHEDULED = "planifié";
    const STATUS_ERROR = "erreur";

    const ENTITY_REFERENCE = "reference";
    const ENTITY_ARTICLE = "article";
    const ENTITY_DELIVERY_ROUND = "tournee";
    const ENTITY_ARRIVAL = "arrivage";
    const ENTITY_REF_LOCATION = "reference_emplacement";

    const ENTITY_LABELS = [
        self::ENTITY_REFERENCE => "Références",
        self::ENTITY_ARTICLE => "Articles",
        self::ENTITY_DELIVERY_ROUND => "Tournées",
        self::ENTITY_ARRIVAL => "Arrivages",
        self::ENTITY_REF_LOCATION => "Référence emplacement"
    ];

    const DESTINATION_EMAIL = 1;
    const DESTINATION_SFTP = 2;

    const PERIOD_INTERVAL_DAY = "day";
    const PERIOD_INTERVAL_WEEK = "week";
    const PERIOD_INTERVAL_MONTH = "month";
    const PERIOD_INTERVAL_YEAR = "year";

    const PERIOD_CURRENT = "current";
    const PERIOD_PREVIOUS = "previous";

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

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $referenceTypes = [];

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $statuses = [];

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $suppliers = [];

    #[ORM\Column(type: "date", nullable: true)]
    private ?DateTime $stockEntryStartDate = null;

    #[ORM\Column(type: "date", nullable: true)]
    private ?DateTime $stockEntryEndDate = null;

    #[ORM\Column(type: "integer", nullable: true)]
    private ?string $destinationType = null;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $ftpParameters = [];

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $recipientEmails = [];

    #[ORM\ManyToMany(targetEntity: Utilisateur::class)]
    private Collection $recipientUsers;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $period = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $periodInterval = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?DateTimeInterface $beganAt = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?DateTimeInterface $endedAt = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?DateTimeInterface $nextExecution = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $error = null;

    #[ORM\OneToOne(mappedBy: 'export', targetEntity: ExportScheduleRule::class, cascade: ["persist"])]
    private ?ExportScheduleRule $exportScheduleRule = null;

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

    public function setEntity(?string $entity): self
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

    public function setForced(?bool $forced): self
    {
        $this->forced = $forced;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTimeInterface $createdAt): self
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

    public function setReferenceTypes(?array $referenceTypes): self
    {
        $this->referenceTypes = $referenceTypes;

        return $this;
    }

    public function getReferenceTypes(): array
    {
        return $this->referenceTypes;
    }

    public function setStatuses(?array $statuses): self
    {
        $this->statuses = $statuses;

        return $this;
    }

    public function getStatuses(): array
    {
        return $this->statuses;
    }

    public function setSuppliers(?array $suppliers): self
    {
        $this->suppliers = $suppliers;

        return $this;
    }

    public function getSuppliers(): array
    {
        return $this->suppliers;
    }

    /**
     * @return DateTime|null
     */
    public function getStockEntryStartDate(): ?DateTime
    {
        return $this->stockEntryStartDate;
    }

    /**
     * @param DateTime|null $stockEntryStartDate
     */
    public function setStockEntryStartDate(?DateTime $stockEntryStartDate): self
    {
        $this->stockEntryStartDate = $stockEntryStartDate;

        return $this;
    }

    /**
     * @return DateTime|null
     */
    public function getStockEntryEndDate(): ?DateTime
    {
        return $this->stockEntryEndDate;
    }

    /**
     * @param DateTime|null $stockEntryEndDate
     */
    public function setStockEntryEndDate(?DateTime $stockEntryEndDate): self
    {
        $this->stockEntryEndDate = $stockEntryEndDate;

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

    public function getDestinationType(): ?string
    {
        return $this->destinationType;
    }

    public function setDestinationType(?string $destinationType): self
    {
        $this->destinationType = $destinationType;

        return $this;
    }

    public function getFtpParameters(): ?array
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

    public function setRecipientUsers($users): self {
        foreach($this->getRecipientUsers() as $user) {
            $this->removeRecipientUser($user);
        }

        foreach($users as $user) {
            $this->addRecipientUser($user);
        }

        return $this;
    }

    public function addRecipientUser(Utilisateur $userEmail): self
    {
        if (!$this->recipientUsers->contains($userEmail)) {
            $this->recipientUsers[] = $userEmail;
        }

        return $this;
    }

    public function removeRecipientUser(Utilisateur $userEmail): self
    {
        $this->recipientUsers->removeElement($userEmail);

        return $this;
    }

    public function getPeriod(): ?string
    {
        return $this->period;
    }

    public function setPeriod(?string $period): self
    {
        $this->period = $period;

        return $this;
    }

    public function getPeriodInterval(): ?string
    {
        return $this->periodInterval;
    }

    public function setPeriodInterval(?string $periodInterval): self
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

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): self
    {
        $this->error = $error;
        return $this;
    }

    public function getExportScheduleRule(): ?ExportScheduleRule
    {
        return $this->exportScheduleRule;
    }

    public function setExportScheduleRule(?ExportScheduleRule $exportScheduleRule): self {
        if($this->exportScheduleRule && $this->exportScheduleRule->getExport() !== $this) {
            $oldExportScheduleRule = $this->exportScheduleRule;
            $this->exportScheduleRule = null;
            $oldExportScheduleRule->setExport(null);
        }
        $this->exportScheduleRule = $exportScheduleRule;
        if($this->exportScheduleRule && $this->exportScheduleRule->getExport() !== $this) {
            $this->exportScheduleRule->setExport($this);
        }

        return $this;
    }

}
