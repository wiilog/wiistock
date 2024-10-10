<?php

namespace App\Entity;

use App\Entity\Interfaces\AttachmentContainer;
use App\Entity\Traits\AttachmentTrait;
use App\Entity\Traits\CleanedCommentTrait;
use App\Repository\DispatchReferenceArticleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DispatchReferenceArticleRepository::class)]
class DispatchReferenceArticle implements AttachmentContainer
{
    use AttachmentTrait;
    use CleanedCommentTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $quantity = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $batchNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sealingNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $serialNumber = null;

    #[ORM\ManyToOne(targetEntity: DispatchPack::class, inversedBy: 'dispatchReferenceArticles')]
    private ?DispatchPack $dispatchPack = null;

    #[ORM\ManyToOne(targetEntity: ReferenceArticle::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?ReferenceArticle $referenceArticle = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false)]
    private ?bool $ADR = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $associatedDocumentTypes = null;

    public function __construct() {
        $this->attachments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getBatchNumber(): ?string
    {
        return $this->batchNumber;
    }

    public function setBatchNumber(?string $batchNumber): self
    {
        $this->batchNumber = $batchNumber;

        return $this;
    }

    public function getSealingNumber(): ?string
    {
        return $this->sealingNumber;
    }

    public function setSealingNumber(?string $sealingNumber): self
    {
        $this->sealingNumber = $sealingNumber;

        return $this;
    }

    public function getSerialNumber(): ?string
    {
        return $this->serialNumber;
    }

    public function setSerialNumber(?string $serialNumber): self
    {
        $this->serialNumber = $serialNumber;

        return $this;
    }

    public function getDispatchPack(): ?DispatchPack
    {
        return $this->dispatchPack;
    }

    public function setDispatchPack(?DispatchPack $dispatchPack): self
    {
        if($this->dispatchPack && $this->dispatchPack !== $dispatchPack) {
            $this->dispatchPack->removeDispatchReferenceArticles($this);
        }
        $this->dispatchPack = $dispatchPack;
        $dispatchPack?->addDispatchReferenceArticles($this);

        return $this;
    }

    public function getReferenceArticle(): ?ReferenceArticle
    {
        return $this->referenceArticle;
    }

    public function setReferenceArticle(?ReferenceArticle $referenceArticle): self
    {
        $this->referenceArticle = $referenceArticle;

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

    public function isADR(): ?bool
    {
        return $this->ADR;
    }

    public function setADR(bool $ADR): self
    {
        $this->ADR = $ADR;

        return $this;
    }

    public function getAssociatedDocumentTypes(): ?array
    {
        return $this->associatedDocumentTypes;
    }

    public function setAssociatedDocumentTypes(?array $associatedDocumentTypes): self
    {
        $this->associatedDocumentTypes = $associatedDocumentTypes;

        return $this;
    }
}
