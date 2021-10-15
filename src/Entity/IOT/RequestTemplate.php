<?php

namespace App\Entity\IOT;

use App\Entity\Traits\FreeFieldsManagerTrait;
use App\Repository\IOT\RequestTemplateRepository;
use App\Entity\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=RequestTemplateRepository::class)
 * @ORM\InheritanceType("JOINED")
 * @ORM\DiscriminatorColumn(name="discr", type="string")
 */
abstract class RequestTemplate {

    use FreeFieldsManagerTrait;

    public const TYPE_HANDLING = 1;
    public const TYPE_DELIVERY = 2;
    public const TYPE_COLLECT = 3;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\ManyToOne(targetEntity=Type::class, inversedBy="requestTemplates")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Type $type = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $name = null;

    /**
     * @ORM\ManyToOne(targetEntity=Type::class, inversedBy="requestTypeTemplates")
     */
    private ?Type $requestType = null;

    /**
     * @ORM\OneToMany(targetEntity=TriggerAction::class, mappedBy="requestTemplate")
     */
    private Collection $triggerActions;

    public function __construct() {
        $this->triggerActions = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getType(): ?Type {
        return $this->type;
    }

    public function setType(?Type $type): self {
        if ($this->type && $this->type !== $type) {
            $this->type->removeRequestTemplate($this);
        }
        $this->type = $type;
        if ($type) {
            $type->addRequestTemplate($this);
        }

        return $this;
    }

    public function getName(): ?string {
        return $this->name;
    }

    public function setName(?string $name): self {
        $this->name = $name;
        return $this;
    }

    public function getRequestType(): ?Type {
        return $this->requestType;
    }

    public function setRequestType(?Type $requestType): self {
        if ($this->requestType && $this->requestType !== $requestType) {
            $this->requestType->removeRequestTypeTemplate($this);
        }
        $this->requestType = $requestType;
        if ($requestType) {
            $requestType->addRequestTypeTemplate($this);
        }

        return $this;
    }

    /**
     * @return Collection|TriggerAction[]
     */
    public function getTriggerActions(): Collection {
        return $this->triggerActions;
    }

    public function addTriggerAction(TriggerAction $triggerAction): self {
        if (!$this->triggerActions->contains($triggerAction)) {
            $this->triggerActions[] = $triggerAction;
            $triggerAction->setRequestTemplate($this);
        }

        return $this;
    }

    public function removeTriggerAction(TriggerAction $triggerAction): self {
        if ($this->triggerActions->removeElement($triggerAction)) {
            if ($triggerAction->getRequestTemplate() === $this) {
                $triggerAction->setRequestTemplate(null);
            }
        }

        return $this;
    }

    public function setTriggerActions(?array $triggerActions): self {
        foreach($this->getTriggerActions()->toArray() as $triggerAction) {
            $this->removeTriggerAction($triggerAction);
        }

        $this->triggerActions = new ArrayCollection();
        foreach($triggerActions as $triggerAction) {
            $this->addTriggerAction($triggerAction);
        }

        return $this;
    }

}
