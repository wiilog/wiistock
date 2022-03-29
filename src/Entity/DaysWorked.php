<?php

namespace App\Entity;

use App\Repository\DaysWorkedRepository;
use Doctrine\ORM\Mapping as ORM;
use WiiCommon\Helper\Stream;

#[ORM\Entity(repositoryClass: DaysWorkedRepository::class)]
class DaysWorked {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $day = null;

    #[ORM\Column(type: 'boolean')]
    private ?bool $worked = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $times = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $displayOrder = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function isWorked(): ?bool {
        return $this->worked;
    }

    public function setWorked(bool $worked): self {
        $this->worked = $worked;

        return $this;
    }

    public function getTimes(): ?string {
        return $this->times;
    }

    /**
     * @return array 12:00-14:00;15:00-16:00 => [[12:00, 14:00], [15:00, 16:00]]
     */
    public function getTimesArray(): array {
        return Stream::explode(';', $this->times ?? '')
            ->filter()
            ->map(fn($day) => Stream::explode('-', $day)->toArray())
            ->toArray();
    }

    public function setTimes(?string $times): self {
        $this->times = $times;

        return $this;
    }

    public function getDay(): ?string {
        return $this->day;
    }

    public function getDisplayDay(): ?string {
        return [
            "monday" => "Lundi",
            "tuesday" => "Mardi",
            "wednesday" => "Mercredi",
            "thursday" => "Jeudi",
            "friday" => "Vendredi",
            "saturday" => "Samedi",
            "sunday" => "Dimanche",
        ][$this->getDay()];
    }

    public function setDay(?string $day): self {
        $this->day = $day;

        return $this;
    }

    public function getDisplayOrder(): ?int {
        return $this->displayOrder;
    }

    public function setDisplayOrder(?int $displayOrder): self {
        $this->displayOrder = $displayOrder;

        return $this;
    }

}
