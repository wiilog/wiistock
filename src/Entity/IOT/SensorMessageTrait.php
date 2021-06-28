<?php


namespace App\Entity\IOT;


use App\Helper\FormatHelper;
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
    public function getSensorMessagesBetween($start, $end, string $type = null): array
    {
        $criteria = Criteria::create();
        if($start) {
            $criteria->andWhere(Criteria::expr()->gte("date", $start));
        }

        if($end) {
            $criteria->andWhere(Criteria::expr()->lte("date", $end));
        }

        $criteria->orderBy(['date' => Criteria::ASC]);

        $messages = $this->getSensorMessages()->matching($criteria);
        if ($type) {
            $messages = $messages
                ->filter(fn(SensorMessage $message) => ($message->getSensor() && FormatHelper::type($message->getSensor()->getType()) === $type));
        }

        return $messages->toArray();
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

    public function removeSensorMessages(array $excludeIds): self{
        foreach ($this->sensorMessages as $sensorMessage) {
            if (!in_array($sensorMessage->getId(), $excludeIds)) {
                $this->sensorMessages->removeElement($sensorMessage);
            }
        }
        return $this;
    }

    public function getLastMessage(): ?SensorMessage {
        $criteria = Criteria::create();
        $orderedSensorMessages = $this->sensorMessages
            ->matching(
                $criteria->orderBy([
                    'date' => Criteria::DESC,
                    'id' => Criteria::DESC,
                ])
            );
        return $orderedSensorMessages->first() ?: null;
    }
}
