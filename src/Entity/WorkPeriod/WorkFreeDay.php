<?php


namespace App\Entity\WorkPeriod;

use App\Repository\WorkPeriod\WorkFreeDayRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkFreeDayRepository::class)]
class WorkFreeDay {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'date', unique: true)]
    private ?DateTime $day = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getDay(): ?DateTime {
        return $this->day;
    }

    public function getTimestamp(): ?int {
        return $this->getDay()?->getTimestamp();
    }

    public function setDay(DateTime $day): self {
        $this->day = $day;
        return $this;
    }
}
