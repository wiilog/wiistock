<?php

namespace App\Entity;

use App\Repository\CartRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CartRepository::class)]
class Cart {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'cart', targetEntity: Utilisateur::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $user = null;

    #[ORM\ManyToMany(targetEntity: ReferenceArticle::class, inversedBy: 'carts')]
    private Collection $references;

    #[ORM\ManyToMany(targetEntity: Article::class, inversedBy: 'carts')]
    private Collection $articles;

    public function __construct() {
        $this->references = new ArrayCollection();
        $this->articles = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getUser(): ?Utilisateur {
        return $this->user;
    }

    public function setUser(?Utilisateur $user): self {
        $this->user = $user;

        return $this;
    }

    public function getReferences(): Collection {
        return $this->references;
    }

    public function addReference(ReferenceArticle $refArticle): self {
        if(!$this->references->contains($refArticle)) {
            $this->references[] = $refArticle;
        }

        return $this;
    }

    public function removeReference(ReferenceArticle $refArticle): self {
        $this->references->removeElement($refArticle);

        return $this;
    }

    public function getArticles(): Collection {
        return $this->articles;
    }

    public function addArticle(Article $article): self {
        if(!$this->articles->contains($article)) {
            $this->articles[] = $article;
        }

        return $this;
    }

    public function removeArticle(Article $article): self {
        $this->articles->removeElement($article);

        return $this;
    }
}
