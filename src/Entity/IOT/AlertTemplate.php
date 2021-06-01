<?php

namespace App\Entity\IOT;

use App\Repository\IOT\AlertTemplateRepository;
use App\Entity\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=AlertTemplateRepository::class)
 */
class AlertTemplate
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\ManyToOne(targetEntity=Type::class, inversedBy="alertTemplates")
     */
    private ?Type $type = null;

    /**
     * @ORM\Column(type="array")
     */
    private array $config = [];

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $name = null;

    /**
     * @ORM\OneToMany(targetEntity=TriggerAction::class, mappedBy="alertTemplate")
     */
    private Collection $triggerActions;

    public function __construct()
    {
        $this->triggerActions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?Type {
        return $this->type;
    }

    public function setType(?Type $type): self {
        if($this->type && $this->type !== $type) {
            $this->type->removeAlertTemplate($this);
        }
        $this->type = $type;
        if($type) {
            $type->addAlertTemplate($this);
        }

        return $this;
    }

    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function setConfig(array $config): self
    {
        $this->config = $config;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getTriggers(): Collection
    {
        return $this->triggerActions;
    }

    public function addTriggerAction(TriggerAction $triggerAction): self
    {
        if (!$this->triggerActions->contains($triggerAction)) {
            $this->triggerActions[] = $triggerAction;
            $triggerAction->setAlertTemplate($this);
        }

        return $this;
    }

    public function removeTriggerAction(TriggerAction $triggerAction): self
    {
        if ($this->triggerActions->removeElement($triggerAction)) {
            // set the owning side to null (unless already changed)
            if ($triggerAction->getAlertTemplate() === $this) {
                $triggerAction->setAlertTemplate(null);
            }
        }

        return $this;
    }

    public function setTriggers(?array $triggerActions): self {
        foreach($this->getTriggers()->toArray() as $trigger) {
            $this->removeTriggerAction($trigger);
        }

        $this->triggerActions = new ArrayCollection();
        foreach($triggerActions as $triggerAction) {
            $this->addTriggerAction($triggerAction);
        }

        return $this;
    }
}
