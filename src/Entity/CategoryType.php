<?php

namespace App\Entity;

use App\Repository\CategoryTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CategoryTypeRepository::class)]
class CategoryType {

    const RECEPTION = 'réception';
    const ARTICLE = 'article';
    const DISPUTE = 'litige';
    const DEMANDE_LIVRAISON = 'demande livraison';
    const DEMANDE_COLLECTE = 'demande collecte';
    const DEMANDE_DISPATCH = 'acheminements';
    const DEMANDE_HANDLING = 'services';
    const ARRIVAGE = 'arrivage';
    const MOUVEMENT_TRACA = 'mouvement traca';
    const TRANSFER_REQUEST = 'demande transfert';
    const SENSOR = 'capteur';
    const REQUEST_TEMPLATE = 'modèle demande';
    const DELIVERY_TRANSPORT = 'demande transport livraison';
    const COLLECT_TRANSPORT = 'demande transport collecte';
    const EXPORT = 'export';

    public const ALL = [
        self::RECEPTION,
        self::ARTICLE,
        self::DISPUTE,
        self::DEMANDE_LIVRAISON,
        self::DEMANDE_COLLECTE,
        self::DEMANDE_DISPATCH,
        self::DEMANDE_HANDLING,
        self::ARRIVAGE,
        self::MOUVEMENT_TRACA,
        self::TRANSFER_REQUEST,
        self::SENSOR,
        self::REQUEST_TEMPLATE,
        self::DELIVERY_TRANSPORT,
        self::COLLECT_TRANSPORT,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $label = null;

    #[ORM\OneToMany(targetEntity: Type::class, mappedBy: 'category')]
    private Collection $types;

    #[ORM\OneToMany(targetEntity: CategorieCL::class, mappedBy: 'categoryType')]
    private Collection $categorieCLs;

    public function __construct() {
        $this->types = new ArrayCollection();
        $this->categorieCLs = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getLabel(): ?string {
        return $this->label;
    }

    public function setLabel(?string $label): self {
        $this->label = $label;

        return $this;
    }

    /**
     * @return Collection|Type[]
     */
    public function getTypes(): Collection {
        return $this->types;
    }

    public function addType(Type $type): self {
        if(!$this->types->contains($type)) {
            $this->types[] = $type;
            $type->setCategory($this);
        }

        return $this;
    }

    public function removeType(Type $type): self {
        if($this->types->contains($type)) {
            $this->types->removeElement($type);
            // set the owning side to null (unless already changed)
            if($type->getCategory() === $this) {
                $type->setCategory(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|CategorieCL[]
     */
    public function getCategorieCLs(): Collection {
        return $this->categorieCLs;
    }

    public function addCategorieCL(CategorieCL $categorieCL): self {
        if(!$this->categorieCLs->contains($categorieCL)) {
            $this->categorieCLs[] = $categorieCL;
            $categorieCL->setCategoryType($this);
        }

        return $this;
    }

    public function removeCategorieCL(CategorieCL $categorieCL): self {
        if($this->categorieCLs->contains($categorieCL)) {
            $this->categorieCLs->removeElement($categorieCL);
            // set the owning side to null (unless already changed)
            if($categorieCL->getCategoryType() === $this) {
                $categorieCL->setCategoryType(null);
            }
        }

        return $this;
    }

}
