<?php

namespace App\Entity\Transport;

use App\Repository\Transport\TransportRequestContactRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransportRequestContactRepository::class)]
class TransportRequestContact
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $fileNumber = null;

    #[ORM\Column(type: 'text')]
    private ?string $address = null;

    #[ORM\Column(type: 'text')]
    private ?string $contact = null;

    #[ORM\Column(type: 'text')]
    private ?string $personToContact = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observation = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getFileNumber(): ?string
    {
        return $this->fileNumber;
    }

    public function setFileNumber(string $fileNumber): self
    {
        $this->fileNumber = $fileNumber;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): self
    {
        $this->address = $address;

        return $this;
    }

    public function getContact(): ?string
    {
        return $this->contact;
    }

    public function setContact(string $contact): self
    {
        $this->contact = $contact;

        return $this;
    }

    public function getPersonToContact(): ?string
    {
        return $this->personToContact;
    }

    public function setPersonToContact(string $personToContact): self
    {
        $this->personToContact = $personToContact;

        return $this;
    }

    public function getObservation(): ?string
    {
        return $this->observation;
    }

    public function setObservation(?string $observation): self
    {
        $this->observation = $observation;

        return $this;
    }
}
