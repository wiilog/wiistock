<?php

namespace App\Entity;

use App\Entity\Interfaces\Serializable;
use App\Entity\Traits\CommentTrait;
use App\Helper\FormatHelper;
use App\Repository\TransferRequestRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=TransferRequestRepository::class)
 */
class TransferRequest implements Serializable {

    const NUMBER_PREFIX = 'DT';

    const DRAFT = "Brouillon";
    const TO_TREAT = "À traiter";
    const TREATED = "Traité";

    use CommentTrait;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $number;

    /**
     * @ORM\ManyToOne(targetEntity=Statut::class, inversedBy="transferRequests")
     * @ORM\JoinColumn(nullable=false)
     */
    private $status;

    /**
     * @ORM\ManyToOne(targetEntity=Utilisateur::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $requester;

    /**
     * @ORM\ManyToOne(targetEntity=Emplacement::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $destination;

    /**
     * @ORM\ManyToOne(targetEntity=Emplacement::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $origin;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $comment;

    /**
     * @ORM\Column(type="datetime")
     */
    private $creationDate;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $validationDate;

    /**
     * @ORM\OneToOne(targetEntity=TransferOrder::class, mappedBy="request")
     */
    private $order;

    /**
     * @ORM\ManyToMany(targetEntity=Article::class, inversedBy="transferRequests")
     */
    private $articles;

    /**
     * @ORM\ManyToMany(targetEntity=ReferenceArticle::class, inversedBy="transferRequests")
     */
    private $references;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Reception", inversedBy="transferRequests")
     */
    private $reception;

    public function __construct() {
        $this->articles = new ArrayCollection();
        $this->references = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getNumber(): ?string {
        return $this->number;
    }

    public function setNumber(string $number): self {
        $this->number = $number;
        return $this;
    }

    public function getReception(): ?Reception
    {
        return $this->reception;
    }

    public function setReception(?Reception $reception): self
    {
        $this->reception = $reception;

        $reception->addTransferRequest($this);

        return $this;
    }

    public function getStatus(): ?Statut {
        return $this->status;
    }

    public function setStatus(?Statut $status): self {
        $oldStatus = $this->status;
        $this->status = $status;

        if (isset($oldStatus) && $oldStatus !== $this->status) {
            $oldStatus->removeTransferRequest($this);
        }

        if (isset($this->status) && $oldStatus !== $this->status) {
            $this->status->addTransferRequest($this);
        }

        return $this;
    }

    public function getRequester(): ?Utilisateur {
        return $this->requester;
    }

    public function setRequester(?Utilisateur $requester): self {
        $this->requester = $requester;
        return $this;
    }

    public function getDestination(): ?Emplacement {
        return $this->destination;
    }

    public function setDestination(?Emplacement $destination): self {
        $this->destination = $destination;
        return $this;
    }

    public function getOrigin(): ?Emplacement {
        return $this->origin;
    }

    public function setOrigin(?Emplacement $origin): self {
        $this->origin = $origin;
        return $this;
    }

    public function getComment(): ?string {
        return $this->comment;
    }

    public function setComment(?string $comment): self {
        $this->comment = $comment;
        $this->setCleanedComment($comment);
        return $this;
    }

    public function getValidationDate(): ?DateTimeInterface {
        return $this->validationDate;
    }

    public function setValidationDate(?DateTimeInterface $validationDate): self {
        $this->validationDate = $validationDate;
        return $this;
    }

    public function getCreationDate(): ?DateTimeInterface {
        return $this->creationDate;
    }

    public function setCreationDate(DateTimeInterface $creationDate): self {
        $this->creationDate = $creationDate;
        return $this;
    }

    public function getOrder(): ?TransferOrder {
        return $this->order;
    }

    public function setOrder(TransferOrder $order): self {
        $this->order = $order;

        // set the owning side of the relation if necessary
        if($order->getRequest() !== $this) {
            $order->setRequest($this);
        }

        return $this;
    }

    /**
     * @return Collection|Article[]
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(Article $article): self
    {
        if (!$this->articles->contains($article)) {
            $this->articles[] = $article;
        }

        return $this;
    }

    public function removeArticle(Article $article): self
    {
        if ($this->articles->contains($article)) {
            $this->articles->removeElement($article);
        }

        return $this;
    }

    /**
     * @return Collection|ReferenceArticle[]
     */
    public function getReferences(): Collection
    {
        return $this->references;
    }

    public function addReference(ReferenceArticle $reference): self
    {
        if (!$this->references->contains($reference)) {
            $this->references[] = $reference;
        }

        return $this;
    }

    public function removeReference(ReferenceArticle $reference): self
    {
        if ($this->references->contains($reference)) {
            $this->references->removeElement($reference);
        }

        return $this;
    }

    public function needsToBeProcessed(): bool {
        $status = $this->getStatus();
        return $status && $status->getNom() === TransferRequest::TO_TREAT;
    }

    public function serialize(): array {
        return [
            'number' => $this->getNumber(),
            'status' => FormatHelper::status($this->getStatus()),
            'requester' => FormatHelper::user($this->getRequester()),
            'origin' => FormatHelper::location($this->getOrigin()),
            'destination' => FormatHelper::location($this->getDestination()),
            'creationDate' => FormatHelper::datetime($this->getCreationDate()),
            'validationDate' => FormatHelper::datetime($this->getValidationDate()),
            'comment' => FormatHelper::html($this->getComment()),
        ];
    }

}
