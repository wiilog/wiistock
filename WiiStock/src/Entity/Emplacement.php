<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\EmplacementRepository")
 */
class Emplacement
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $nom;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $status;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Racks", mappedBy="emplacement")
     */
    private $racks;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Articles", mappedBy="direction")
     */
    private $articles;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Articles", mappedBy="position")
     */
    private $position;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Preparation", mappedBy="destination")
     */
    private $preparations;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Livraison", mappedBy="destination")
     */
    private $livraisons;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Demande", mappedBy="destination")
     */
    private $demandes;

    public function __construct()
    {
        $this->racks = new ArrayCollection();
        $this->articles = new ArrayCollection();
        $this->position = new ArrayCollection();
        $this->preparations = new ArrayCollection();
        $this->livraisons = new ArrayCollection();
        $this->demandes = new ArrayCollection();
       
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return Collection|Racks[]
     */
    public function getRacks(): Collection
    {
        return $this->racks;
    }

    public function addRack(Racks $rack): self
    {
        if (!$this->racks->contains($rack)) {
            $this->racks[] = $rack;
            $rack->setEmplacement($this);
        }

        return $this;
    }

    public function removeRack(Racks $rack): self
    {
        if ($this->racks->contains($rack)) {
            $this->racks->removeElement($rack);
            // set the owning side to null (unless already changed)
            if ($rack->getEmplacement() === $this) {
                $rack->setEmplacement(null);
            }
        }

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
            $article->setDirection($this);
        }

        return $this;
    }

    public function removeArticle(Articles $article): self
    {
        if ($this->articles->contains($article)) {
            $this->articles->removeElement($article);
            // set the owning side to null (unless already changed)
            if ($article->getDirection() === $this) {
                $article->setDirection(null);
            }
        }

        return $this;
    }

    public function __toString()
    {
        return $this->nom;
    }

    /**
     * @return Collection|Articles[]
     */
    public function getPosition(): Collection
    {
        return $this->position;
    }

    public function addPosition(Articles $position): self
    {
        if (!$this->position->contains($position)) {
            $this->position[] = $position;
            $position->setPosition($this);
        }

        return $this;
    }

    public function removePosition(Articles $position): self
    {
        if ($this->position->contains($position)) {
            $this->position->removeElement($position);
            // set the owning side to null (unless already changed)
            if ($position->getPosition() === $this) {
                $position->setPosition(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Preparation[]
     */
    public function getPreparations(): Collection
    {
        return $this->preparations;
    }

    public function addPreparation(Preparation $preparation): self
    {
        if (!$this->preparations->contains($preparation)) {
            $this->preparations[] = $preparation;
            $preparation->setDestination($this);
        }

        return $this;
    }

    public function removePreparation(Preparation $preparation): self
    {
        if ($this->preparations->contains($preparation)) {
            $this->preparations->removeElement($preparation);
            // set the owning side to null (unless already changed)
            if ($preparation->getDestination() === $this) {
                $preparation->setDestination(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Livraison[]
     */
    public function getLivraisons(): Collection
    {
        return $this->livraisons;
    }

    public function addLivraison(Livraison $livraison): self
    {
        if (!$this->livraisons->contains($livraison)) {
            $this->livraisons[] = $livraison;
            $livraison->setDestination($this);
        }

        return $this;
    }

    public function removeLivraison(Livraison $livraison): self
    {
        if ($this->livraisons->contains($livraison)) {
            $this->livraisons->removeElement($livraison);
            // set the owning side to null (unless already changed)
            if ($livraison->getDestination() === $this) {
                $livraison->setDestination(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Demande[]
     */
    public function getDemandes(): Collection
    {
        return $this->demandes;
    }

    public function addDemande(Demande $demande): self
    {
        if (!$this->demandes->contains($demande)) {
            $this->demandes[] = $demande;
            $demande->setDestination($this);
        }

        return $this;
    }

    public function removeDemande(Demande $demande): self
    {
        if ($this->demandes->contains($demande)) {
            $this->demandes->removeElement($demande);
            // set the owning side to null (unless already changed)
            if ($demande->getDestination() === $this) {
                $demande->setDestination(null);
            }
        }

        return $this;
    }

}
