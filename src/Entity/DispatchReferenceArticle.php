<?php

namespace App\Entity;

use App\Repository\DispatchReferenceArticleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DispatchReferenceArticleRepository::class)]
class DispatchReferenceArticle
{
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

    #[ORM\OneToMany(targetEntity: Attachment::class)]
    private Collection $attachments;

    public function __construct()
    {
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
        $this->dispatchPack = $dispatchPack;

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

    /**
     * @return Collection<int, Attachment>
     */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    public function addAttachment(Attachment $attachment): self
    {
        if (!$this->attachments->contains($attachment)) {
            $this->attachments->add($attachment);
        }

        return $this;
    }

    public function removeAttachment(Attachment $attachment): self
    {
        $this->attachments->removeElement($attachment);

        return $this;
    }
}
