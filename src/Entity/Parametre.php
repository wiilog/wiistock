<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ParametreRepository")
 */
class Parametre
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
    private $label;

    /**
     * @ORM\Column(type="string", length=32)
     */
    private $typage;

    /**
     * @ORM\Column(type="json_array", nullable=true)
     */
    private $elements;

	/**
	 * @ORM\Column(type="string", length=255)
	 */
    private $default;

	/**
	 * @ORM\OneToMany(targetEntity="App\Entity\ParametreRole", mappedBy="parametre")
	 */
	private $parametreRoles;

    public function __construct()
    {
        $this->parametreRoles = new ArrayCollection();
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getElements()
    {
        return $this->elements;
    }

    public function setElements($elements): self
    {
        $this->elements = $elements;

        return $this;
    }

    /**
     * @return Collection|ParametreRole[]
     */
    public function getParametreRoles(): Collection
    {
        return $this->parametreRoles;
    }

    public function addParametreRole(ParametreRole $parametreRole): self
    {
        if (!$this->parametreRoles->contains($parametreRole)) {
            $this->parametreRoles[] = $parametreRole;
            $parametreRole->setParametre($this);
        }

        return $this;
    }

    public function removeParametreRole(ParametreRole $parametreRole): self
    {
        if ($this->parametreRoles->contains($parametreRole)) {
            $this->parametreRoles->removeElement($parametreRole);
            // set the owning side to null (unless already changed)
            if ($parametreRole->getParametre() === $this) {
                $parametreRole->setParametre(null);
            }
        }

        return $this;
    }

    public function getTypage(): ?string
    {
        return $this->typage;
    }

    public function setTypage(string $typage): self
    {
        $this->typage = $typage;

        return $this;
    }

    public function getDefault(): ?string
    {
        return $this->default;
    }

    public function setDefault(string $default): self
    {
        $this->default = $default;

        return $this;
    }
}
