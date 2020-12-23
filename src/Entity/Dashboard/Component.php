<?php

namespace App\Entity\Dashboard;

use App\Repository\Dashboard as DashboardRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DashboardRepository\ComponentRepository::class)
 * @ORM\Table(name="dashboard_component",
 *    uniqueConstraints={
 *        @ORM\UniqueConstraint(name="video_unique", columns={"column_index", "row_id"})
 *    }
 * )
 */
class Component
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=ComponentType::class, inversedBy="componentsUsing")
     * @ORM\JoinColumn(nullable=false)
     */
    private $type;

    /**
     * @ORM\ManyToOne(targetEntity=PageRow::class, inversedBy="components")
     * @ORM\JoinColumn(nullable=false)
     */
    private $row;

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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?ComponentType
    {
        return $this->type;
    }

    public function setType(?ComponentType $type): self
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

    public function getRow(): ?PageRow
    {
        return $this->row;
    }

    public function setRow(?PageRow $row): self
    {
        $this->row = $row;

        return $this;
    }
}
