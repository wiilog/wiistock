<?php

namespace App\Entity;

use App\Repository\StorageRuleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StorageRuleRepository::class)]
class StorageRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Emplacement::class)]
    private ?Emplacement $location = null;

    #[ORM\ManyToOne(targetEntity: ReferenceArticle::class, inversedBy: "storageRules")]
    private ?ReferenceArticle $referenceArticle = null;

    #[ORM\Column]
    private ?int $securityQuantity = null;

    #[ORM\Column]
    private ?int $conditioningQuantity = null;

    public function __construct() {
        $this->securityQuantity = 0;
        $this->conditioningQuantity = 0;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSecurityQuantity(): ?int
    {
        return $this->securityQuantity;
    }

    public function setSecurityQuantity(int $securityQuantity): self
    {
        $this->securityQuantity = $securityQuantity;

        return $this;
    }

    public function getConditioningQuantity(): ?int
    {
        return $this->conditioningQuantity;
    }

    public function setConditioningQuantity(int $conditioningQuantity): self
    {
        $this->conditioningQuantity = $conditioningQuantity;

        return $this;
    }

    /**
     * @return Emplacement|null
     */
    public function getLocation(): ?Emplacement
    {
        return $this->location;
    }

    /**
     * @param Emplacement|null $location
     */
    public function setLocation(?Emplacement $location): void
    {
        $this->location = $location;
    }

    /**
     * @return ReferenceArticle|null
     */
    public function getReferenceArticle(): ?ReferenceArticle
    {
        return $this->referenceArticle;
    }

    /**
     * @param ReferenceArticle|null $referenceArticle
     */
    public function setReferenceArticle(?ReferenceArticle $referenceArticle): self
    {
        if($this->referenceArticle && $this->referenceArticle !== $referenceArticle) {
            $this->referenceArticle->removeStorageRule($this);
        }
        $this->referenceArticle = $referenceArticle;
        $referenceArticle?->addStorageRule($this);

        return $this;
    }
}
