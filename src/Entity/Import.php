<?php

namespace App\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ImportRepository")
 */
class Import
{
	const STATUS_DRAFT = 'brouillon';
	const STATUS_CANCELLED = 'annulé';
	const STATUS_IN_PROGRESS = 'en cours';
	const STATUS_FINISHED = 'terminé';
	const STATUS_PLANNED = 'planifié';

	const ENTITY_ART = 'ART';
	const ENTITY_REF= 'REF';
	const ENTITY_FOU = 'FOU';
	const ENTITY_ART_FOU = 'ART_FOU';

	const FIELDS_NEEDED = [
        self::ENTITY_ART_FOU => [
            'référence article de référence',
            'référence fournisseur',
            'reference',
        ],
        self::ENTITY_ART => [
            'référence article de référence',
            'label',
            'emplacement',
        ],
        self::ENTITY_FOU => [
            'codeReference',
            'nom'
        ],
        self::ENTITY_REF => [
            'reference',
            'libelle',
            'type',
            'emplacement',
            'typeQuantite'
        ]
    ];

	const FIELD_PK = [
	    self::ENTITY_ART_FOU => 'reference',
        self::ENTITY_ART => 'barCode',
        self::ENTITY_FOU => 'codeReference',
        self::ENTITY_REF => 'reference'
    ];

	const FIELDS_ENTITY = [
        'reference' => 'référence',
        'barCode' => 'code barre',
        'quantite' => 'quantité',
        'label' => 'libellé',
        'libelle' => 'libellé',
        'articleFournisseur' => 'article fournisseur',
        'needsMobileSync' => 'Synchronisation nomade',
        'prixUnitaire' => 'prix unitaire',
        'limitSecurity' => 'seuil de sécurité',
        'limitWarning' => "seuil d'alerte",
        'quantiteStock' => 'quantité en stock',
        'typeQuantite' => 'type quantité (article ou référence)',
        'codeReference' => 'code',
        'nom' => 'libellé',
        'referenceReference' => 'référence article de référence',
        'fournisseurReference' => 'référence fournisseur',
        'emplacement' => 'emplacement',
        'catInv' => 'catégorie inventaire',
        'articleFournisseurReference' => 'articleFournisseurReference',
		'typeLabel' => 'type',
        'dateLastInventory' => 'date dernier inventaire (jj/mm/AAAA)',
        'emergencyComment' => 'commentaire urgence',
        'batch' => 'Lot',
        'expiryDate' => 'Date de péremption (jj/mm/AAAA)',
        'stockEntryDate' => 'Date d\'entrée en stock (jj/mm/AAAA hh:MM)',
	];

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
     * @ORM\OneToOne(targetEntity="Attachment", inversedBy="importCsv")
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
     * @var bool
	 * @ORM\Column(type="boolean", nullable=false)
	 */
    private $forced;

	/**
     * @var bool
	 * @ORM\Column(type="boolean", nullable=false)
	 */
    private $flash;

	/**
     * @var DateTime
	 * @ORM\Column(type="datetime", nullable=false)
	 */
    private $createdAt;

	/**
	 * @ORM\Column(type="integer", nullable=true)
	 */
    private $nbErrors;

	/**
	 * @ORM\Column(type="datetime", nullable=true)
	 */
    private $startDate;

	/**
	 * @ORM\Column(type="datetime", nullable=true)
	 */
    private $endDate;

	/**
     * @var Attachment
	 * @ORM\OneToOne(targetEntity="Attachment", inversedBy="importLog")
	 */
    private $logFile;

	/**
	 * @ORM\Column(type="json", nullable=true)
	 */
    private $columnToField;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\MouvementStock", mappedBy="import")
     */
    private $mouvements;

    public function __construct() {
        $this->createdAt = new DateTime();
        $this->mouvements = new ArrayCollection();
        $this->forced = false;
        $this->flash = false;
    }

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

    public function getCsvFile(): ?Attachment
    {
        return $this->csvFile;
    }

    public function setCsvFile(?Attachment $csvFile): self
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

    public function getStartDate(): ?DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(DateTime $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?DateTime
    {
        return $this->endDate;
    }

    public function setEndDate(DateTime $endDate): self
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

    public function getLogFile(): ?Attachment
    {
        return $this->logFile;
    }

    public function setLogFile(?Attachment $logFile): self
    {
        if (isset($this->logFile)) {
            $this->logFile->setImportLog(null);
        }

        $this->logFile = $logFile;

        if (isset($this->logFile)) {
            $this->logFile->setImportLog($this);
        }

        return $this;
    }

    /**
     * @return Collection|MouvementStock[]
     */
    public function getMouvements(): Collection
    {
        return $this->mouvements;
    }

    public function addMouvement(MouvementStock $mouvement): self
    {
        if (!$this->mouvements->contains($mouvement)) {
            $this->mouvements[] = $mouvement;
            $mouvement->setImport($this);
        }

        return $this;
    }

    public function removeMouvement(MouvementStock $mouvement): self
    {
        if ($this->mouvements->contains($mouvement)) {
            $this->mouvements->removeElement($mouvement);
            // set the owning side to null (unless already changed)
            if ($mouvement->getImport() === $this) {
                $mouvement->setImport(null);
            }
        }

        return $this;
    }

    public function isForced(): bool {
        return $this->forced;
    }

    public function setForced(bool $forced): self {
        $this->forced = $forced;
        return $this;
    }

    public function isFlash(): bool {
        return $this->flash;
    }

    public function setFlash(bool $flash): self {
        $this->flash = $flash;
        return $this;
    }

    public function getCreateAt(): DateTime {
        return $this->createdAt;
    }

}
