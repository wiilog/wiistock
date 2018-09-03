<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\FilialesRepository")
 */
class Filiales
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $nom;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Parcs", inversedBy="filiales")
     */
    private $parc;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Sites", mappedBy="filiale")
     */
    private $sites;

    public function __construct()
    {
        $this->sites = new ArrayCollection();
    }

    public function getId(): ?int
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

    public function getParc(): ?Parcs
    {
        return $this->parc;
    }

    public function setParc(?Parcs $parc): self
    {
        $this->parc = $parc;

        return $this;
    }

    /**
     * @return Collection|Sites[]
     */
    public function getSites(): Collection
    {
        return $this->sites;
    }

    public function addSite(Sites $site): self
    {
        if (!$this->sites->contains($site)) {
            $this->sites[] = $site;
            $site->setFiliale($this);
        }

        return $this;
    }

    public function removeSite(Sites $site): self
    {
        if ($this->sites->contains($site)) {
            $this->sites->removeElement($site);
            // set the owning side to null (unless already changed)
            if ($site->getFiliale() === $this) {
                $site->setFiliale(null);
            }
        }

        return $this;
    }
}
