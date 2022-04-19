<?php

namespace App\Entity\Transport;

use App\Entity\Nature;
use App\Repository\Transport\TransportDeliveryRequestLineRepository;
use App\Repository\Transport\TransportRequestLineRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransportRequestLineRepository::class)]
#[ORM\InheritanceType('JOINED')]
abstract class TransportRequestLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Nature::class)]
    private ?Nature $nature = null;

    #[ORM\ManyToOne(targetEntity: TransportRequest::class, inversedBy: 'lines')]
    private ?TransportRequest $request = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getNature(): ?Nature {
        return $this->nature;
    }

    public function setNature(?Nature $nature): self {
        $this->nature = $nature;

        return $this;
    }

    public function getRequest(): ?TransportRequest {
        return $this->request;
    }

    public function setRequest(?TransportRequest $request): self {
        if($this->request && $this->request !== $request) {
            $this->request->removeLine($this);
        }
        $this->request = $request;
        $request?->addLine($this);

        return $this;
    }
}
