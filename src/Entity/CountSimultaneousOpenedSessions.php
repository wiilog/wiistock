<?php

namespace App\Entity;

use App\Repository\CountSimultaneousOpenedSessionsRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CountSimultaneousOpenedSessionsRepository::class)]
class CountSimultaneousOpenedSessions
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTime $dateTime = null;

    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private ?int $count = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }

    public function setCount(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    public function getDateTime(): ?DateTime
    {
        return $this->dateTime;
    }

    public function setDateTime(DateTime $dateTime): self
    {
        $this->dateTime = $dateTime;

        return $this;
    }
}
