<?php


namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;

trait RequestTrait {

    /**
     * @ORM\Column(type="boolean", nullable=false, options={"default": 1})
     */
    private ?bool $filled = true;

    public function isFilled(): ?bool
    {
        return $this->filled;
    }

    public function setFilled(?bool $filled): self
    {
        $this->filled = $filled;

        return $this;
    }

}
