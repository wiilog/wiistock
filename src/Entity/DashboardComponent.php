<?php

namespace App\Entity;

use App\Repository\DashboardComponentRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DashboardComponentRepository::class)
 */
class DashboardComponent
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=DashboardComponentType::class, inversedBy="componentsUsing")
     */
    private $type;

    /**
     * @ORM\Column(type="integer")
     */
    private $columnIndex;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $title;

    /**
     * @ORM\Column(type="json")
     */
    private $config = [];

    /**
     * @ORM\ManyToOne(targetEntity=DashboardPageRow::class, inversedBy="components")
     */
    private $row;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?DashboardComponentType
    {
        return $this->type;
    }

    public function setType(?DashboardComponentType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getColumnIndex(): ?int
    {
        return $this->columnIndex;
    }

    public function setColumnIndex(int $columnIndex): self
    {
        $this->columnIndex = $columnIndex;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

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

    public function getRow(): ?DashboardPageRow
    {
        return $this->row;
    }

    public function setRow(?DashboardPageRow $row): self
    {
        $this->row = $row;

        return $this;
    }
}
