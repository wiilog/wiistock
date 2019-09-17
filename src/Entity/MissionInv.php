<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\MissionInvRepository")
 */
class MissionInv
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="date")
     */
    private $startPrevDate;

    /**
     * @ORM\Column(type="date")
     */
    private $endPrevDate;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\ReferenceArticle", inversedBy="missionInvs")
     */
    private $refArticle;

    public function __construct()
    {
        $this->refArticle = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStartPrevDate(): ?\DateTimeInterface
    {
        return $this->startPrevDate;
    }

    public function setStartPrevDate(\DateTimeInterface $startPrevDate): self
    {
        $this->startPrevDate = $startPrevDate;

        return $this;
    }

    public function getEndPrevDate(): ?\DateTimeInterface
    {
        return $this->endPrevDate;
    }

    public function setEndPrevDate(\DateTimeInterface $endPrevDate): self
    {
        $this->endPrevDate = $endPrevDate;

        return $this;
    }

    /**
     * @return Collection|ReferenceArticle[]
     */
    public function getRefArticle(): Collection
    {
        return $this->refArticle;
    }

    public function addRefArticle(ReferenceArticle $refArticle): self
    {
        if (!$this->refArticle->contains($refArticle)) {
            $this->refArticle[] = $refArticle;
        }

        return $this;
    }

    public function removeRefArticle(ReferenceArticle $refArticle): self
    {
        if ($this->refArticle->contains($refArticle)) {
            $this->refArticle->removeElement($refArticle);
        }

        return $this;
    }
}
