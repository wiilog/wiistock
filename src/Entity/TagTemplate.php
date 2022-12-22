<?php

namespace App\Entity;

use App\Repository\TagTemplateRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TagTemplateRepository::class)]
class TagTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $prefix = null;

    #[ORM\Column]
    private ?bool $barcodeOrQr = null;

    #[ORM\Column]
    private ?int $height = null;

    #[ORM\Column]
    private ?int $width = null;

    #[ORM\Column(length: 255)]
    private ?string $module = null;

    #[ORM\ManyToMany(targetEntity: Nature::class, inversedBy: 'tags')]
    private Collection $natures;

    #[ORM\ManyToMany(targetEntity: Type::class, inversedBy: 'tags')]
    private Collection $types;

    public function __construct()
    {
        $this->natures = new ArrayCollection();
        $this->types = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPrefix(): ?string
    {
        return $this->prefix;
    }

    public function setPrefix(string $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function isBarcode(): ?bool {
        return $this->barcodeOrQr == true;
    }

    public function isQRcode(): ?bool {
        return $this->barcodeOrQr == false;
    }

    public function setBarcodeOrQr(bool $barcodeOrQr): self {
        $this->barcodeOrQr = $barcodeOrQr;

        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(int $height): self
    {
        $this->height = $height;

        return $this;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setWidth(int $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function getModule(): ?string
    {
        return $this->module;
    }

    public function setModule(string $module): self
    {
        $this->module = $module;

        return $this;
    }

    /**
     * @return Collection<int, Nature>
     */
    public function getNatures(): Collection
    {
        return $this->natures;
    }

    public function addNature(Nature $nature): self
    {
        if (!$this->natures->contains($nature)) {
            $this->natures->add($nature);
        }

        return $this;
    }

    public function removeNature(Nature $nature): self
    {
        $this->natures->removeElement($nature);

        return $this;
    }

    /**
     * @return Collection<int, Type>
     */
    public function getTypes(): Collection
    {
        return $this->types;
    }

    public function addType(Type $type): self
    {
        if (!$this->types->contains($type)) {
            $this->types->add($type);
        }

        return $this;
    }

    public function removeType(Type $type): self
    {
        $this->types->removeElement($type);

        return $this;
    }
}
