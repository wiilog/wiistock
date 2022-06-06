<?php

namespace App\Entity\Transport;

use App\Repository\TransportRoundStartingHourRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Utilisateur;

#[ORM\Entity(repositoryClass: TransportRoundStartingHourRepository::class)]
class TransportRoundStartingHour
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    private $hour;

    #[ORM\OneToMany(mappedBy: 'transportRoundStartingHour', targetEntity: Utilisateur::class)]
    private $deliverers;

    public function __construct()
    {
        $this->deliverers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHour(): ?string
    {
        return $this->hour;
    }

    public function setHour(string $hour): self
    {
        $this->hour = $hour;

        return $this;
    }

    /**
     * @return Collection<int, Utilisateur>
     */
    public function getDeliverers(): Collection
    {
        return $this->deliverers;
    }

    public function addDeliverer(Utilisateur $deliverer): self
    {
        if (!$this->deliverers->contains($deliverer)) {
            $this->deliverers[] = $deliverer;
            $deliverer->setTransportRoundStartingHour($this);
        }

        return $this;
    }

    public function removeDeliverer(Utilisateur $deliverer): self
    {
        if ($this->deliverers->removeElement($deliverer)) {
            // set the owning side to null (unless already changed)
            if ($deliverer->getTransportRoundStartingHour() === $this) {
                $deliverer->setTransportRoundStartingHour(null);
            }
        }

        return $this;
    }
}
