<?php

namespace App\Entity;

use App\Repository\WiilockRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WiilockRepository::class)]
class Wiilock {

    public const DASHBOARD_FED_KEY = 'dashboard_is_being_fed';
    public const INACTIVE_SESSIONS_CLEAN_KEY = 'inactive_sessions_are_being_cleaned';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    private $lockKey;

    #[ORM\Column(type: 'boolean')]
    private $value;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $updateDate;

    public function getId(): ?int {
        return $this->id;
    }

    public function getLockKey(): ?string {
        return $this->lockKey;
    }

    public function setLockKey(string $lockKey): self {
        $this->lockKey = $lockKey;

        return $this;
    }

    public function getValue(): ?bool {
        return $this->value;
    }

    public function setValue(bool $value): self {
        $this->value = $value;

        return $this;
    }

    public function getUpdateDate(): ?\DateTimeInterface {
        return $this->updateDate;
    }

    public function setUpdateDate(?\DateTimeInterface $updateDate): self {
        $this->updateDate = $updateDate;

        return $this;
    }

}
