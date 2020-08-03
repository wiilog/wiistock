<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\CategoryTypeRepository")
 */
class CategoryType
{
//    const TYPE_ARTICLE = 'typeArticle';
    const RECEPTION = 'réception';
    const ARTICLE = 'article';
    const LITIGE = 'litige';
    const DEMANDE_LIVRAISON = 'demande livraison';
    const DEMANDE_COLLECTE = 'demande collecte';
    const ARRIVAGE = 'arrivage';
    const MOUVEMENT_TRACA = 'mouvement traca';
//    const MOUVEMENT = 'mouvement';
//    const ART_REFS = 'articles et références CEA';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $label;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Type", mappedBy="category")
     */
    private $types;

    public function __construct()
    {
        $this->types = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @return Collection|Type[]
     */
    public function getTypes(): Collection
    {
        return $this->types;
    }

    public function addType(Type $type): self
    {
        if (!$this->types->contains($type)) {
            $this->types[] = $type;
            $type->setCategory($this);
        }

        return $this;
    }

    public function removeType(Type $type): self
    {
        if ($this->types->contains($type)) {
            $this->types->removeElement($type);
            // set the owning side to null (unless already changed)
            if ($type->getCategory() === $this) {
                $type->setCategory(null);
            }
        }

        return $this;
    }
}
