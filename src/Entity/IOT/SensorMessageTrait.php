<?php


namespace App\Entity\IOT;


use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\IOT\SensorMessage as SensorMessage;
use WiiCommon\Helper\Stream;

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
    public function getSensorMessagesBetween($start, $end, $type): array
    {
        $criteria = Criteria::create();
        if($start) {
            $criteria->andWhere(Criteria::expr()->gte("date", $start));
        }

        if($end) {
            $criteria->andWhere(Criteria::expr()->lte("date", $end));
        }

        $messages = $this->getSensorMessages()->matching($criteria);

        if ($type) {
            $messages = Stream::from($messages)
                ->filter(fn(SensorMessage $message) => $message->getSensor()->getType() === $type)
                ->toArray();
        }
        return $messages;
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
