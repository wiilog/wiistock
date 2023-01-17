<?php

namespace App\Entity;

use App\Repository\DispatchReferenceArticleRepository;
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

    #[ORM\ManyToOne( targetEntity: DispatchPack::class, inversedBy: 'dispatchReferenceArticles')]
    private ?DispatchPack $dispatchPack = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?ReferenceArticle $referenceArticle = null;

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

    public function setBatch(?string $batchNumber): self
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
}
