<?php


namespace App\Entity\IOT;


use App\Entity\IOT\SensorMessage as SensorMessage;
use App\Helper\FormatHelper;
use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\Mapping as ORM;

trait SensorMessageTrait {

    #[ORM\ManyToMany(targetEntity: SensorMessage::class)]
    private Collection $sensorMessages;

    /**
     * @return Collection|SensorMessage[]
     */
    public function getSensorMessages(): Collection {
        return $this->sensorMessages;
    }

    /**
     * @return SensorMessage[]
     */
    public function getSensorMessagesBetween($start, $end, ?array $options = []): array {
        $sensorType = $options['sensorType'] ?? null;
        $messageContentType = $options['messageContentType'] ?? null;

        $criteria = Criteria::create();
        if($start) {
            if (!($start instanceof DateTime)) {
                $start = DateTime::createFromFormat("Y-m-d\TH:i", $start, new \DateTimeZone('Europe/Paris'));
            }
            $criteria->andWhere(Criteria::expr()->gte("date", $start));
        }

        if($end) {
            if (!($end instanceof DateTime)) {
                $end = DateTime::createFromFormat("Y-m-d\TH:i", $end, new \DateTimeZone('Europe/Paris'));
            }
            $criteria->andWhere(Criteria::expr()->lte("date", $end));
        }

        if($messageContentType) {
            $criteria->andWhere(Criteria::expr()->eq("contentType", $messageContentType));
        }

        $criteria->orderBy(['date' => Criteria::ASC]);

        $messages = $this->getSensorMessages()->matching($criteria);
        if($sensorType) {
            $messages = $messages
                ->filter(fn(SensorMessage $message) => ($message->getSensor() && FormatHelper::type($message->getSensor()->getType()) === $sensorType));
        }

        return $messages->toArray();
    }

    public function addSensorMessage(SensorMessage $sensorMessage): self
    {
        $this->sensorMessages[] = $sensorMessage;
        return $this;
    }

    public function removeSensorMessage(SensorMessage $sensorMessage): self {
        if($this->sensorMessages->contains($sensorMessage)) {
            $this->sensorMessages->removeElement($sensorMessage);
        }
        return $this;
    }

    public function setSensorMessage($sensorMessages): self {
        foreach($sensorMessages as $sensorMessage) {
            $this->addSensorMessage($sensorMessage);
        }

        return $this;
    }

    public function clearSensorMessages(): self {
        $this->sensorMessages->clear();

        return $this;
    }

    public function removeSensorMessages(array $excludeIds): self {
        foreach($this->sensorMessages as $sensorMessage) {
            if(!in_array($sensorMessage->getId(), $excludeIds)) {
                $this->sensorMessages->removeElement($sensorMessage);
            }
        }
        return $this;
    }

    public function getLastMessage(): ?SensorMessage {
        $orderedSensorMessages = $this->sensorMessages
            ->matching(
                Criteria::create()
                    ->orderBy([
                        'date' => Order::Descending,
                        'id' => Order::Descending,
                    ])
                    ->setMaxResults(1)
            );
        return $orderedSensorMessages->first() ?: null;
    }

}
