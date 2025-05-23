<?php

namespace App\Entity;

use App\Repository\RoleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RoleRepository::class)]
class Role {

    public const LANDING_PAGE_DASHBOARD = 'dashboard';
    public const LANDING_PAGE_TRANSPORT_PLANNING = 'transport_planning';
    public const LANDING_PAGE_TRANSPORT_REQUEST = 'transport_request';

    public const NO_ACCESS_USER = 'aucun accès';
    public const SUPER_ADMIN = 'super admin';
    public const CLIENT_UTIL = 'Client utilisation';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private ?string $label = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $quantityType = null;

    #[ORM\ManyToMany(targetEntity: 'Action', mappedBy: 'roles')]
    private Collection $actions;

    #[ORM\OneToMany(mappedBy: 'role', targetEntity: Utilisateur::class)]
    private Collection $users;

    #[ORM\Column(type: 'string', options: ["default" => self::LANDING_PAGE_DASHBOARD])]
    private ?string $landingPage = self::LANDING_PAGE_DASHBOARD;

    /**
     * @var Collection<int, Statut>
     */
    #[ORM\ManyToMany(targetEntity: Statut::class, mappedBy: 'authorizedRequestCreationRoles')]
    private Collection $authorizedRequestStatuses;

    public function __construct() {
        $this->actions = new ArrayCollection();
        $this->users = new ArrayCollection();
        $this->authorizedRequestStatuses = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getLabel(): ?string {
        return $this->label;
    }

    public function setLabel(string $label): self {
        $this->label = $label;

        return $this;
    }

    /**
     * @return Collection|Action[]
     */
    public function getActions(): Collection {
        return $this->actions;
    }

    public function addAction(Action $action): self {
        if(!$this->actions->contains($action)) {
            $this->actions[] = $action;
            $action->addRole($this);
        }

        return $this;
    }

    public function removeAction(Action $action): self {
        if($this->actions->removeElement($action)) {
            $action->removeRole($this);
        }

        return $this;
    }

    /**
     * @param Action[] $actions
     */
    public function setActions(array $actions): self {
        foreach($this->getActions()->toArray() as $action) {
            $this->removeAction($action);
        }

        $this->actions = new ArrayCollection();

        foreach($actions as $action) {
            $this->addAction($action);
        }

        return $this;
    }

    /**
     * @return Collection<Utilisateur>
     */
    public function getUsers(): Collection {
        return $this->users;
    }

    public function addUser(Utilisateur $user): self {
        if(!$this->users->contains($user)) {
            $this->users[] = $user;
            $user->setRole($this);
        }

        return $this;
    }

    public function removeUser(Utilisateur $user): self {
        if($this->users->contains($user)) {
            $this->users->removeElement($user);
            // set the owning side to null (unless already changed)
            if($user->getRole() === $this) {
                $user->setRole(null);
            }
        }

        return $this;
    }

    public function getQuantityType(): ?string {
        return $this->quantityType;
    }

    public function setQuantityType(?string $quantityType): self {
        $this->quantityType = $quantityType;
        return $this;
    }

    public function getLandingPage(): ?string {
        return $this->landingPage;
    }

    public function setLandingPage(string $landingPage): self {
        $this->landingPage = $landingPage;
        return $this;
    }

    /**
     * @return Collection<int, Statut>
     */
    public function getAuthorizedRequestStatuses(): Collection
    {
        return $this->authorizedRequestStatuses;
    }

    public function addAuthorizedDispatchStatus(Statut $status): static
    {
        if (!$this->authorizedRequestStatuses->contains($status)) {
            $this->authorizedRequestStatuses[] = $status;
            $status->addAuthorizedRequestCreationRole($this);
        }

        return $this;
    }

    public function removeAuthorizedRequestStatus(Statut $status): static
    {
        if ($this->authorizedRequestStatuses->removeElement($status)) {
            $status->removeAuthorizedRequestCreationRole($this);
        }

        return $this;
    }

    public function setAuthorizedRequestStatuses(?iterable $statuses): self {
        foreach($this->getAuthorizedRequestStatuses()->toArray() as $statuses) {
            $this->removeAuthorizedRequestStatus($statuses);
        }

        $this->authorizedRequestStatuses = new ArrayCollection();
        foreach($statuses ?? [] as $statuses) {
            $this->addAuthorizedDispatchStatus($statuses);
        }

        return $this;
    }


}
