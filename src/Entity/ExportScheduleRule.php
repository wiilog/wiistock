<?php

namespace App\Entity;

use App\Entity\Interfaces\Frequency;
use App\Entity\Traits\FrequencyTrait;
use App\Repository\ExportScheduleRuleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExportScheduleRuleRepository::class)]
class ExportScheduleRule implements Frequency {

    use FrequencyTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'exportScheduleRule', targetEntity: Export::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Export $export = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getExport(): ?Export {
        return $this->export;
    }

    public function setExport(?Export $export): self {
        if ($this->getExport() && $this->getExport() !== $export) {
            $this->getExport()->setExportScheduleRule(null);
        }

        $this->export = $export;

        if($export && $export->getExportScheduleRule() !== $this) {
            $export->setExportScheduleRule($this);
        }

        return $this;
    }
}
