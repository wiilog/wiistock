<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ArticlesRepository")
 */
class Articles
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
    private $etat;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Emplacements")
     */
    private $emplacement;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ReferencesArticles")
     */
    private $reference;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $quantite;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $photo;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Zones", inversedBy="articles")
     */
    private $zone;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Quais", inversedBy="articles")
     */
    private $quai;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $reference_CEA;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $libelle_CEA;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Contenu", mappedBy="articles")
     */
    private $contenu;

    public function __construct()
    {
        $this->entrees = new ArrayCollection();
        $this->sorties = new ArrayCollection();
        $this->transferts = new ArrayCollection();
        $this->contenu = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getEtat(): ?string
    {
        return $this->etat;
    }

    public function setEtat(string $etat): self
    {
        $this->etat = $etat;

        return $this;
    }

    public function getEmplacement(): ?Emplacements
    {
        return $this->emplacement;
    }

    public function setEmplacement(?Emplacements $emplacement): self
    {
        $this->emplacement = $emplacement;

        return $this;
    }

    public function getReference(): ?ReferencesArticles
    {
        return $this->reference;
    }

    public function setReference(?ReferencesArticles $reference): self
    {
        $this->reference = $reference;

        return $this;
    }


    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(?int $quantite): self
    {
        $this->quantite = $quantite;

        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): self
    {
        $this->photo = $photo;

        return $this;
    }

    public function getZone(): ?Zones
    {
        return $this->zone;
    }

    public function setZone(?Zones $zone): self
    {
        $this->zone = $zone;

        return $this;
    }

    public function getQuai(): ?Quais
    {
        return $this->quai;
    }

    public function setQuai(?Quais $quai): self
    {
        $this->quai = $quai;

        return $this;
    }

    public function getReferenceCEA(): ?string
    {
        return $this->reference_CEA;
    }

    public function setReferenceCEA(?string $reference_CEA): self
    {
        $this->reference_CEA = $reference_CEA;

        return $this;
    }

    public function getLibelleCEA(): ?string
    {
        return $this->libelle_CEA;
    }

    public function setLibelleCEA(?string $libelle_CEA): self
    {
        $this->libelle_CEA = $libelle_CEA;

        return $this;
    }

    /**
     * @return Collection|Contenu[]
     */
    public function getContenu(): Collection
    {
        return $this->contenu;
    }

    public function addContenu(Contenu $contenu): self
    {
        if (!$this->contenu->contains($contenu)) {
            $this->contenu[] = $contenu;
            $contenu->setArticles($this);
        }

        return $this;
    }

    public function removeContenu(Contenu $contenu): self
    {
        if ($this->contenu->contains($contenu)) {
            $this->contenu->removeElement($contenu);
            // set the owning side to null (unless already changed)
            if ($contenu->getArticles() === $this) {
                $contenu->setArticles(null);
            }
        }

        return $this;
    }
}
