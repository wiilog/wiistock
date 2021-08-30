<?php

namespace App\Entity;

use App\Repository\VisibilityGroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=VisibilityGroupRepository::class)
 */
class VisibilityGroup {

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $label = null;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $description = null;

    /**
     * @ORM\OneToMany(targetEntity=Utilisateur::class, mappedBy="visibilityGroup")
     */
    private Collection $users;

    /**
     * @ORM\ManyToMany(targetEntity=ReferenceArticle::class, inversedBy="visibilityGroups")
     */
    private Collection $articleReferences;

    public function __construct() {
        $this->users = new ArrayCollection();
        $this->articleReferences = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getLabel(): ?string {
        return $this->label;
    }

    public function setLabel(string $label): self {
        $this->label = $label;

        return $this;
    }

    public function getDescription(): ?string {
        return $this->description;
    }

    public function setDescription(?string $description): self {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Collection|Utilisateur[]
     */
    public function getUsers(): Collection {
        return $this->users;
    }

    public function addUser(Utilisateur $user): self {
        if (!$this->users->contains($user)) {
            $this->users[] = $user;
            $user->setVisibilityGroup($this);
        }

        return $this;
    }

    public function removeUser(Utilisateur $user): self {
        if ($this->users->removeElement($user)) {
            if ($user->getVisibilityGroup() === $this) {
                $user->setVisibilityGroup(null);
            }
        }

        return $this;
    }

    public function setUsers(?array $users): self {
        foreach($this->getUsers()->toArray() as $user) {
            $this->removeUser($user);
        }

        $this->users = new ArrayCollection();
        foreach($users as $user) {
            $this->addUser($user);
        }

        return $this;
    }

    /**
     * @return Collection|ReferenceArticle[]
     */
    public function getArticleReferences(): Collection {
        return $this->articleReferences;
    }

    public function addArticleReference(ReferenceArticle $articleReference): self {
        if (!$this->articleReferences->contains($articleReference)) {
            $this->articleReferences[] = $articleReference;
            $articleReference->addVisibilityGroup($this);
        }

        return $this;
    }

    public function removeArticleReference(ReferenceArticle $articleReference): self {
        if ($this->articleReferences->removeElement($articleReference)) {
            $articleReference->removeVisibilityGroup($this);
        }

        return $this;
    }

    public function setArticleReferences(?array $articleReferences): self {
        foreach($this->getArticleReferences()->toArray() as $articleReference) {
            $this->removeArticleReference($articleReference);
        }

        $this->articleReferences = new ArrayCollection();
        foreach($articleReferences as $articleReference) {
            $this->addArticleReference($articleReference);
        }

        return $this;
    }
}
