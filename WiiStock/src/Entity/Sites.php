<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Serializer\Annotation\Groups;
/**
 * @ORM\Entity(repositoryClass="App\Repository\SitesRepository")
 */
class Sites
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
     * @ORM\ManyToOne(targetEntity="App\Entity\Filiales", inversedBy="sites")
     */
    private $filiale;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Parcs", mappedBy="site")
     */
    private $parcs;

    public function __construct()
    {
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

    public function getFiliale(): ?Filiales
    {
        return $this->filiale;
    }

    public function setFiliale(?Filiales $filiale): self
    {
        $this->filiale = $filiale;

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
            $parc->setSite($this);
        }

        return $this;
    }

    public function removeParc(Parcs $parc): self
    {
        if ($this->parcs->contains($parc)) {
            $this->parcs->removeElement($parc);
            // set the owning side to null (unless already changed)
            if ($parc->getSite() === $this) {
                $parc->setSite(null);
            }
        }

        return $this;
    }
}
