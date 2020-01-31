<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\RoleRepository")
 */
class Role
{
    const NO_ACCESS_USER = 'aucun accès';
    const CLIENT_UTIL = 'Client utilisation';
    const DEM_SAFRAN = 'Demandeur Safran';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=64, unique=true)
     */
    private $label;

    /**
     * @ORM\ManyToMany(targetEntity="Action", mappedBy="roles")
     */
    private $actions;

    /**
     * @ORM\Column(type="boolean")
     */
    private $active;

    /**
     * @ORM\OneToMany(targetEntity="Utilisateur", mappedBy="role")
     */
    private $users;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ParametreRole", mappedBy="role")
     */
    private $parametreRoles;

    public function __construct()
    {
        $this->actions = new ArrayCollection();
        $this->users = new ArrayCollection();
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

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    /**
     * @return Collection|Action[]
     */
    public function getActions(): Collection
    {
        return $this->actions;
    }

    public function addAction(Action $action): self
    {
        if (!$this->actions->contains($action)) {
            $this->actions[] = $action;
            $action->addRole($this);
        }

        return $this;
    }

    public function removeAction(Action $action): self
    {
        if ($this->actions->contains($action)) {
            $this->actions->removeElement($action);
            $action->removeRole($this);
        }

        return $this;
    }

    /**
     * @return Collection|Utilisateur[]
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(Utilisateur $user): self
    {
        if (!$this->users->contains($user)) {
            $this->users[] = $user;
            $user->setRole($this);
        }

        return $this;
    }

    public function removeUser(Utilisateur $user): self
    {
        if ($this->users->contains($user)) {
            $this->users->removeElement($user);
            // set the owning side to null (unless already changed)
            if ($user->getRole() === $this) {
                $user->setRole(null);
            }
        }

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
            $parametreRole->setRole($this);
        }

        return $this;
    }

    public function removeParametreRole(ParametreRole $parametreRole): self
    {
        if ($this->parametreRoles->contains($parametreRole)) {
            $this->parametreRoles->removeElement($parametreRole);
            // set the owning side to null (unless already changed)
            if ($parametreRole->getRole() === $this) {
                $parametreRole->setRole(null);
            }
        }

        return $this;
    }
}
