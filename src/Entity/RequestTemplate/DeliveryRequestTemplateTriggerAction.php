<?php

namespace App\Entity\RequestTemplate;

use App\Entity\Attachment;
use App\Entity\Emplacement;
use App\Entity\ReferenceArticle;
use App\Repository\RequestTemplate\DeliveryRequestTemplateTriggerActionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeliveryRequestTemplateTriggerActionRepository::class)]
class DeliveryRequestTemplateTriggerAction extends RequestTemplate implements DeliveryRequestTemplateInterface  {

    use DeliveryRequestTemplateTrait;

    #[ORM\OneToMany(mappedBy: 'deliveryRequestTemplateTriggerAction', targetEntity: RequestTemplateLineReference::class, cascade: ["remove"])]
    private Collection $lines;

    public function __construct() {
        parent::__construct();
        $this->lines = new ArrayCollection();
    }

    /**
     * @return Collection<int, RequestTemplateLineReference>
     */
    public function getLines(): Collection {
        return $this->lines;
    }

    public function setLines(iterable $lines): self {
        foreach ($this->lines as $line) {
            $this->removeLine($line);
        }

        foreach ($lines as $line) {
            $this->addLine($line);
        }
    }

    public function addLine(ReferenceArticle $ref): self {
        if(!$this->lines->contains($ref)) {
            $this->lines[] = $ref;
        }

        return $this;
    }

    public function removeLine(ReferenceArticle $ref): self {
        $this->lines->removeElement($ref);

        return $this;
    }

    public function getUsage(): DeliveryRequestTemplateUsageEnum {
        return DeliveryRequestTemplateUsageEnum::TRIGGER_ACTION;
    }
}
