<?php


namespace App\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\WorkFreeDayRepository")
 */
class WorkFreeDay
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="date", unique=true)
     * @var DateTime A "Y-m-d" formatted value
     */
    private $day;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return DateTime
     */
    public function getDay(): DateTime
    {
        return $this->day;
    }


    public function getTimestamp()
    {
        return $this->getDay()->getTimestamp();
    }

    /**
     * @param DateTime $day
     * @return WorkFreeDay
     */
    public function setDay(DateTime $day): self
    {
        $this->day = $day;
        return $this;
    }
}
