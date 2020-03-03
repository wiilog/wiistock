<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ImportRepository")
 */
class Import
{
	const STATUS_CANCELLED = 'annulé';
	const STATUS_IN_PROGRESS = 'en cours';
	const STATUS_FINISHED = 'terminé';
	const STATUS_PLANNED = 'planifié';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $label;

    /**
     * @ORM\Column(type="string", length=64)
     */
    private $entity;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\PieceJointe", cascade={"persist", "remove"})
     */
    private $csvFile;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Statut")
     */
    private $status;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Utilisateur")
	 */
    private $user;

	/**
	 * @ORM\Column(type="integer", nullable=true)
	 */
    private $newEntries;

	/**
	 * @ORM\Column(type="integer", nullable=true)
	 */
    private $updatedEntries;

	/**
	 * @ORM\Column(type="integer", nullable=true)
	 */
    private $nbErrors;

	/**
	 * @ORM\Column(type="datetime")
	 */
    private $startDate;

	/**
	 * @ORM\Column(type="datetime")
	 */
    private $endDate;

	/**
	 * @ORM\OneToOne(targetEntity="App\Entity\PieceJointe", cascade={"persist", "remove"})
	 */
    private $logFile;

	/**
	 * @ORM\Column(type="json", nullable=true)
	 */
    private $columnToField;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
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

    public function getCsvFile(): ?PieceJointe
    {
        return $this->csvFile;
    }

    public function setCsvFile(?PieceJointe $csvFile): self
    {
        $this->csvFile = $csvFile;

        return $this;
    }

    public function getNewEntries(): ?int
    {
        return $this->newEntries;
    }

    public function setNewEntries(?int $newEntries): self
    {
        $this->newEntries = $newEntries;

        return $this;
    }

    public function getUpdatedEntries(): ?int
    {
        return $this->updatedEntries;
    }

    public function setUpdatedEntries(?int $updatedEntries): self
    {
        $this->updatedEntries = $updatedEntries;

        return $this;
    }

    public function getNbErrors(): ?int
    {
        return $this->nbErrors;
    }

    public function setNbErrors(?int $nbErrors): self
    {
        $this->nbErrors = $nbErrors;

        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getColumnToField(): ?array
    {
        return $this->columnToField;
    }

    public function setColumnToField(?array $columnToField): self
    {
        $this->columnToField = $columnToField;

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

    public function getLogFile(): ?PieceJointe
    {
        return $this->logFile;
    }

    public function setLogFile(?PieceJointe $logFile): self
    {
        $this->logFile = $logFile;

        return $this;
    }
}
