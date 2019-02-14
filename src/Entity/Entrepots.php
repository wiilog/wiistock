<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\EntrepotsRepository")
 */
class Entrepots
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
     * @ORM\OneToMany(targetEntity="App\Entity\Allees", mappedBy="entrepots", orphanRemoval=true)
     * @Groups({"entrepots", "emplacements"})
     */
    private $allees;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Quais", mappedBy="entrepots")
     * @Groups({"entrepots"})
     */
    private $quais;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Zones", mappedBy="entrepots")
     * @Groups({"entrepots", "emplacements"})
     */
    private $zones;

    public function __construct()
    {
        $this->allees = new ArrayCollection();
        $this->quais = new ArrayCollection();
        $this->zones = new ArrayCollection();
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
     * @return Collection|Allees[]
     */
    public function getAllees(): Collection
    {
        return $this->allees;
    }

    public function addAllee(Allees $allee): self
    {
        if (!$this->allees->contains($allee)) {
            $this->allees[] = $allee;
            $allee->setEntrepots($this);
        }

        return $this;
    }

    public function removeAllee(Allees $allee): self
    {
        if ($this->allees->contains($allee)) {
            $this->allees->removeElement($allee);
            // set the owning side to null (unless already changed)
            if ($allee->getEntrepots() === $this) {
                $allee->setEntrepots(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Quais[]
     */
    public function getQuais(): Collection
    {
        return $this->quais;
    }

    public function addQuai(Quais $quai): self
    {
        if (!$this->quais->contains($quai)) {
            $this->quais[] = $quai;
            $quai->setEntrepots($this);
        }

        return $this;
    }

    public function removeQuai(Quais $quai): self
    {
        if ($this->quais->contains($quai)) {
            $this->quais->removeElement($quai);
            // set the owning side to null (unless already changed)
            if ($quai->getEntrepots() === $this) {
                $quai->setEntrepots(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Zones[]
     */
    public function getZones(): Collection
    {
        return $this->zones;
    }

    public function addZone(Zones $zone): self
    {
        if (!$this->zones->contains($zone)) {
            $this->zones[] = $zone;
            $zone->setEntrepots($this);
        }

        return $this;
    }

    public function removeZone(Zones $zone): self
    {
        if ($this->zones->contains($zone)) {
            $this->zones->removeElement($zone);
            // set the owning side to null (unless already changed)
            if ($zone->getEntrepots() === $this) {
                $zone->setEntrepots(null);
            }
        }

        return $this;
    }
}
