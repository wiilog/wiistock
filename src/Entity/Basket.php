<?php

namespace App\Entity;

use App\Repository\BasketRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=BasketRepository::class)
 */
class Basket
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\OneToOne(targetEntity=utilisateur::class, inversedBy="basket", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @ORM\ManyToMany(targetEntity=ReferenceArticle::class, inversedBy="baskets")
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

    public function getUser(): ?utilisateur
    {
        return $this->user;
    }

    public function setUser(?utilisateur $user): self
    {
        $this->user = $user;

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
        $this->refArticle->removeElement($refArticle);

        return $this;
    }
}
