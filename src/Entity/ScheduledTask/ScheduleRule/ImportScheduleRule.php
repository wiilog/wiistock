<?php

namespace App\Entity\ScheduledTask\ScheduleRule;

use App\Entity\ScheduledTask\Import;
use App\Repository\ScheduledTask\StorageRule\ImportScheduleRuleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImportScheduleRuleRepository::class)]
class ImportScheduleRule extends ScheduleRule {

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: "scheduleRule", targetEntity: Import::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Import $import = null;

    #[ORM\Column(type: Types::STRING, nullable: false)]
    private ?string $filePath = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getImport(): ?Import {
        return $this->import;
    }

    public function setImport(?Import $import): self {
        if ($this->getImport() && $this->getImport() !== $import) {
            $this->getImport()->setScheduleRule(null);
        }

        $this->import = $import;

        if($import->getScheduleRule() !== $this) {
            $import->setScheduleRule($this);
        }

        return $this;
    }

    public function getFilePath(): ?string {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): self {
        $this->filePath = $filePath;
        return $this;
    }

}
