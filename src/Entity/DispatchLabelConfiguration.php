<?php

namespace App\Entity;

use App\Repository\DispatchLabelConfigurationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DispatchLabelConfigurationRepository::class)]
class DispatchLabelConfiguration {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Type::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Type $type = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    private ?string $codeType = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $showPacksCount = null;

    #[ORM\OneToMany(mappedBy: "dispatchLabelConfiguration", targetEntity: DispatchLabelConfigurationField::class, cascade: ["persist", "remove"], orphanRemoval: true)]
    private Collection $fields;

    public function __construct()
    {
        $this->fields = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getType(): ?Type {
        return $this->type;
    }

    public function setType(?Type $type): self {
        $this->type = $type;

        return $this;
    }

    public function getCodeType(): ?string {
        return $this->codeType;
    }

    public function setCodeType(string $codeType): self {
        $this->codeType = $codeType;

        return $this;
    }

    public function getShowPacksCount(): ?bool {
        return $this->showPacksCount;
    }

    public function setShowPacksCount(bool $showPacksCount): self {
        $this->showPacksCount = $showPacksCount;

        return $this;
    }

    /**
     * @return Collection|DispatchLabelConfigurationField[]
     */
    public function getFields(): Collection {
        return $this->fields;
    }

    public function addField(DispatchLabelConfigurationField $field): self {
        if (!$this->fields->contains($field)) {
            $this->fields[] = $field;
            $field->setDispatchLabelConfiguration($this);
        }

        return $this;
    }

    public function removeField(DispatchLabelConfigurationField $field): self {
        if ($this->fields->removeElement($field)) {
            if ($field->getDispatchLabelConfiguration() === $this) {
                $field->setDispatchLabelConfiguration(null);
            }
        }

        return $this;
    }

    public function setFields(?array $fields): self {
        foreach($this->getFields()->toArray() as $field) {
            $this->removeField($field);
        }

        $this->fields = new ArrayCollection();
        foreach($fields as $field) {
            $this->addField($field);
        }

        return $this;
    }

}
