<?php


namespace App\Entity\IOT;


use App\Entity\Attachment;
use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\IOT\SensorMessage as SensorMessage;

trait SensorMessageTrait
{

    /**
     * @ORM\ManyToMany(targetEntity=SensorMessage::class)
     */
    private Collection $sensorMessages;

    /**
     * @return Collection|SensorMessage[]
     */
    public function getSensorMessages(): Collection
    {
        return $this->sensorMessages;
    }

    /**
     * @return Collection|SensorMessage[]
     */
    public function getSensorMessagesBetween($start, $end): Collection
    {
        $criteria = Criteria::create();
        if($start) {
            $criteria->andWhere(Criteria::expr()->gte("date", $start));
        }

        if($end) {
            $criteria->andWhere(Criteria::expr()->lte("date", $end));
        }

        return $this->getSensorMessages()->matching($criteria);
    }

    public function addSensorMessage(SensorMessage $sensorMessage): self
    {
        if (!$this->sensorMessages->contains($sensorMessage)) {
            $this->sensorMessages[] = $sensorMessage;
        }
        return $this;
    }

    public function removeSensorMessage(SensorMessage $sensorMessage): self
    {
        if ($this->sensorMessages->contains($sensorMessage)) {
            $this->sensorMessages->removeElement($sensorMessage);
        }
        return $this;
    }

    public function setSensorMessage($sensorMessages): self
    {
        foreach($sensorMessages as $sensorMessage) {
            $this->addSensorMessage($sensorMessage);
        }

        return $this;
    }

    public function clearSensorMessages(): self{
        $this->sensorMessages->clear();

        return $this;
    }

    public function removeIfNotIn(array $ids): self{
        foreach ($this->sensorMessages as $sensorMessage) {
            if (!in_array($sensorMessage->getId(), $ids)) {
                $this->sensorMessages->removeElement($sensorMessage);
            }
        }
        return $this;
    }
}
