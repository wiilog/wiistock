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
    private ?string $batch = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sealing = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $series = null;

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

    public function getBatch(): ?string
    {
        return $this->batch;
    }

    public function setBatch(?string $batch): self
    {
        $this->batch = $batch;

        return $this;
    }

    public function getSealing(): ?string
    {
        return $this->sealing;
    }

    public function setSealing(?string $sealing): self
    {
        $this->sealing = $sealing;

        return $this;
    }

    public function getSeries(): ?string
    {
        return $this->series;
    }

    public function setSeries(?string $series): self
    {
        $this->series = $series;

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
