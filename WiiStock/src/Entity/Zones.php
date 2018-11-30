<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ZonesRepository")
 */
class Zones
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Groups({"entrepots", "emplacements"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"entrepots", "emplacements"})
     */
    private $nom;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Entrepots", inversedBy="zones")
     */
    private $entrepots;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Articles", mappedBy="zone")
     */
    private $articles;

    public function __construct()
    {
        $this->articles = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function getEntrepots(): ?Entrepots
    {
        return $this->entrepots;
    }

    public function setEntrepots(?Entrepots $entrepots): self
    {
        $this->entrepots = $entrepots;

        return $this;
    }

    /**
     * @return Collection|Articles[]
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(Articles $article): self
    {
        if (!$this->articles->contains($article)) {
            $this->articles[] = $article;
            $article->setZone($this);
        }

        return $this;
    }

    public function removeArticle(Articles $article): self
    {
        if ($this->articles->contains($article)) {
            $this->articles->removeElement($article);
            // set the owning side to null (unless already changed)
            if ($article->getZone() === $this) {
                $article->setZone(null);
            }
        }

        return $this;
    }
}
