<?php

namespace App\Entity;

use App\Repository\StorageRuleRepository;
use Doctrine\ORM\Mapping as ORM;


#[ORM\UniqueConstraint(name: self::uniqueConstraintLocationReferenceArticleName, columns: ["location_id", "reference_article_id"])]
#[ORM\Entity(repositoryClass: StorageRuleRepository::class)]
class StorageRule
{
    const uniqueConstraintLocationReferenceArticleName = "storage_rule_location_reference_article_unique";

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Emplacement::class)]
    private Emplacement $location;

    #[ORM\ManyToOne(targetEntity: ReferenceArticle::class, inversedBy: "storageRules")]
    private ?ReferenceArticle $referenceArticle = null;

    #[ORM\Column(type: "integer", nullable: false)]
    private int $securityQuantity;

    #[ORM\Column(type: "integer", nullable: false)]
    private int $conditioningQuantity;

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
    public function setLocation(?Emplacement $location): self
    {
        $this->location = $location;

        return $this;
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
        if($this->referenceArticle
            && $this->referenceArticle !== $referenceArticle
        ) {
            $this->referenceArticle->removeStorageRule($this);
        }
        $this->referenceArticle = $referenceArticle;
        $referenceArticle?->addStorageRule($this);

        return $this;
    }
}
