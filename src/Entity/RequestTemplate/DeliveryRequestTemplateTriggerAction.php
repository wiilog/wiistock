<?php

namespace App\Entity\RequestTemplate;

use App\Entity\Attachment;
use App\Entity\Emplacement;
use App\Entity\ReferenceArticle;
use App\Repository\RequestTemplate\DeliveryRequestTemplateRepositoryTriggerAction;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeliveryRequestTemplateRepositoryTriggerAction::class)]
class DeliveryRequestTemplateTriggerAction extends RequestTemplate implements DeliveryRequestTemplateInterface  {

    use DeliveryRequestTemplateTrait;

    #[ORM\OneToMany(mappedBy: 'deliveryRequestTemplateTriggerAction', targetEntity: RequestTemplateLine::class, cascade: ["remove"])]
    private Collection $lines;

    public function __construct() {
        parent::__construct();
        $this->lines = new ArrayCollection();
    }

    /**
     * @return Collection<RequestTemplateLine>
     */
    public function getLines(): Collection {
        return $this->lines;
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
