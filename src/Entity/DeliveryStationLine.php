<?php

namespace App\Entity;

use App\Entity\Type\Type;
use App\Repository\DeliveryStationLineRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeliveryStationLineRepository::class)]
class DeliveryStationLine
{

    public const REFERENCE_FIXED_FIELDS = [
        'type' => [
            'label' => 'Type',
            'value' => 'type',
        ],
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Type $deliveryType = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?VisibilityGroup $visibilityGroup = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Emplacement $destinationLocation = null;

    #[ORM\Column(nullable: true)]
    private array $filters = [];

    #[ORM\Column(type: Types::TEXT)]
    private ?string $welcomeMessage = null;

    #[ORM\ManyToMany(targetEntity: Utilisateur::class)]
    private Collection $receivers;

    #[ORM\Column(type: 'string')]
    private ?string $token = null;

    public function __construct()
    {
        $this->receivers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDeliveryType(): ?Type
    {
        return $this->deliveryType;
    }

    public function setDeliveryType(?Type $deliveryType): self
    {
        $this->deliveryType = $deliveryType;

        return $this;
    }

    public function getVisibilityGroup(): ?VisibilityGroup
    {
        return $this->visibilityGroup;
    }

    public function setVisibilityGroup(?VisibilityGroup $visibilityGroup): self
    {
        $this->visibilityGroup = $visibilityGroup;

        return $this;
    }

    public function getDestinationLocation(): ?Emplacement
    {
        return $this->destinationLocation;
    }

    public function setDestinationLocation(?Emplacement $destinationLocation): self
    {
        $this->destinationLocation = $destinationLocation;

        return $this;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function setFilters(?array $filters): self
    {
        $this->filters = $filters;

        return $this;
    }

    public function getWelcomeMessage(): ?string
    {
        return $this->welcomeMessage;
    }

    public function setWelcomeMessage(string $welcomeMessage): self
    {
        $this->welcomeMessage = $welcomeMessage;

        return $this;
    }

    /**
     * @return Collection<int, Utilisateur>
     */
    public function getReceivers(): Collection
    {
        return $this->receivers;
    }

    public function addReceiver(Utilisateur $receiver): self
    {
        if (!$this->receivers->contains($receiver)) {
            $this->receivers->add($receiver);
        }

        return $this;
    }

    public function removeReceiver(Utilisateur $receiver): self
    {
        $this->receivers->removeElement($receiver);

        return $this;
    }

    public function setReceivers(?iterable $receivers): self {
        foreach($this->getReceivers()->toArray() as $receiver){
            $this->removeReceiver($receiver);
        }

        $this->receivers = new ArrayCollection();
        foreach ($receivers ?? [] as $receiver) {
            $this->addReceiver($receiver);
        }

        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): self
    {
        $this->token = $token;

        return $this;
    }
}
