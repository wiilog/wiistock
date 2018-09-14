<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Serializer\Annotation\Groups;
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
     * @Groups({"parc"})
     */
    private $nom;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Sites", mappedBy="filiale")
     * @ORM\OrderBy({"nom" = "ASC"})
     */
    private $sites;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Parcs", mappedBy="filiale")
     */
    private $parcs;

    public function __construct()
    {
        $this->sites = new ArrayCollection();
        $this->parcs = new ArrayCollection();
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

    /**
     * @return Collection|Parcs[]
     */
    public function getParcs(): Collection
    {
        return $this->parcs;
    }

    public function addParc(Parcs $parc): self
    {
        if (!$this->parcs->contains($parc)) {
            $this->parcs[] = $parc;
            $parc->setFiliale($this);
        }

        return $this;
    }

    public function removeParc(Parcs $parc): self
    {
        if ($this->parcs->contains($parc)) {
            $this->parcs->removeElement($parc);
            // set the owning side to null (unless already changed)
            if ($parc->getFiliale() === $this) {
                $parc->setFiliale(null);
            }
        }

        return $this;
    }
}
