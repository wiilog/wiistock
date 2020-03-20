<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ChampLibreRepository")
 */
class ChampLibre
{
    const TYPE_BOOL = 'booleen';
    const TYPE_TEXT = 'text';
    const TYPE_NUMBER = 'number';
    const TYPE_LIST = 'list';
    const TYPE_LIST_MULTIPLE = 'list multiple';
    const TYPE_DATE = 'date';
    const TYPE_DATETIME = 'datetime';
    const TYPAGE = [
        [
            'value' => ChampLibre::TYPE_BOOL,
            'label' => 'Oui/Non',
        ],
        [
            'value' => ChampLibre::TYPE_DATE,
            'label' => 'Date',
        ],
        [
            'value' => ChampLibre::TYPE_DATETIME,
            'label' => 'Date et heure',
        ],
        [
            'value' => ChampLibre::TYPE_LIST,
            'label' => 'Liste',
        ],
        [
            'value' => ChampLibre::TYPE_LIST_MULTIPLE,
            'label' => 'Liste multiple',
        ],
        [
            'value' => ChampLibre::TYPE_NUMBER,
            'label' => 'Nombre',
        ],
        [
            'value' => ChampLibre::TYPE_TEXT,
            'label' => 'Texte',
        ],
    ];
    const TYPAGE_ARR = [
        ChampLibre::TYPE_BOOL => 'Oui/Non',
        ChampLibre::TYPE_DATE => 'Date',
        ChampLibre::TYPE_DATETIME => 'Date et heure',
        ChampLibre::TYPE_LIST => 'Liste',
        ChampLibre::TYPE_NUMBER => 'Nombre',
        ChampLibre::TYPE_TEXT => 'Texte',
        ChampLibre::TYPE_LIST_MULTIPLE => 'Liste multiple'
    ];
    const SPECIC_COLLINS_BL = 'BL';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true, unique=true)
     */
    private $label;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Type", inversedBy="champsLibres")
     */
    private $type;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $typage;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $defaultValue;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ValeurChampLibre", mappedBy="champLibre")
     */
    private $valeurChampsLibres;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\FiltreRef", mappedBy="champLibre")
     */
    private $filters;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $elements = [];

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $requiredCreate;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $requiredEdit;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\CategorieCL", inversedBy="champsLibres")
     */
    private $categorieCL;


    public function __construct()
    {
        $this->valeurChampsLibres = new ArrayCollection();
        $this->filters = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->label;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getType(): ?Type
    {
        return $this->type;
    }

    public function setType(?Type $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getTypage(): ?string
    {
        return $this->typage;
    }

    public function setTypage(?string $typage): self
    {
        $this->typage = $typage;

        return $this;
    }

    public function getDefaultValue(): ?string
    {
        return $this->defaultValue;
    }

    public function setDefaultValue(?string $defaultValue): self
    {
        $this->defaultValue = $defaultValue;

        return $this;
    }

    /**
     * @return Collection|ValeurChampLibre[]
     */
    public function getValeurChampsLibres(): Collection
    {
        return $this->valeurChampsLibres;
    }

    public function addValeurChampLibre(ValeurChampLibre $valeurChampLibre): self
    {
        if (!$this->valeurChampsLibres->contains($valeurChampLibre)) {
            $this->valeurChampsLibres[] = $valeurChampLibre;
            $valeurChampLibre->setChampLibre($this);
        }

        return $this;
    }

    public function removeValeurChampLibre(ValeurChampLibre $valeurChampLibre): self
    {
        if ($this->valeurChampsLibres->contains($valeurChampLibre)) {
            $this->valeurChampsLibres->removeElement($valeurChampLibre);
            // set the owning side to null (unless already changed)
            if ($valeurChampLibre->getChampLibre() === $this) {
                $valeurChampLibre->setChampLibre(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|FiltreRef[]
     */
    public function getFilters(): Collection
    {
        return $this->filters;
    }

    public function addFilter(FiltreRef $filter): self
    {
        if (!$this->filters->contains($filter)) {
            $this->filters[] = $filter;
            $filter->setChampLibre($this);
        }

        return $this;
    }

    public function removeFilter(FiltreRef $filter): self
    {
        if ($this->filters->contains($filter)) {
            $this->filters->removeElement($filter);
            // set the owning side to null (unless already changed)
            if ($filter->getChampLibre() === $this) {
                $filter->setChampLibre(null);
            }
        }

        return $this;
    }

    public function getElements(): ?array
    {
        return $this->elements;
    }

    public function setElements(?array $elements): self
    {
        $this->elements = $elements;

        return $this;
    }

    public function getRequiredCreate(): ?bool
    {
        return $this->requiredCreate;
    }

    public function setRequiredCreate(?bool $requiredCreate): self
    {
        $this->requiredCreate = $requiredCreate;

        return $this;
    }

    public function getRequiredEdit(): ?bool
    {
        return $this->requiredEdit;
    }

    public function setRequiredEdit(?bool $requiredEdit): self
    {
        $this->requiredEdit = $requiredEdit;

        return $this;
    }

    public function getCategorieCL(): ?CategorieCL
    {
        return $this->categorieCL;
    }

    public function setCategorieCL(?CategorieCL $categorieCL): self
    {
        $this->categorieCL = $categorieCL;

        return $this;
    }

    public function addValeurChampsLibre(ValeurChampLibre $valeurChampsLibre): self
    {
        if (!$this->valeurChampsLibres->contains($valeurChampsLibre)) {
            $this->valeurChampsLibres[] = $valeurChampsLibre;
            $valeurChampsLibre->setChampLibre($this);
        }

        return $this;
    }

    public function removeValeurChampsLibre(ValeurChampLibre $valeurChampsLibre): self
    {
        if ($this->valeurChampsLibres->contains($valeurChampsLibre)) {
            $this->valeurChampsLibres->removeElement($valeurChampsLibre);
            // set the owning side to null (unless already changed)
            if ($valeurChampsLibre->getChampLibre() === $this) {
                $valeurChampsLibre->setChampLibre(null);
            }
        }

        return $this;
    }
}
