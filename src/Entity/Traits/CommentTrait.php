<?php


namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;

trait CommentTrait {

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $smartComment;

    public function getSmartComment(): ?string
    {
        return $this->smartComment;
    }

    public function setSmartComment(?string $comment): self
    {
        $this->smartComment = strip_tags($comment);

        return $this;
    }
}
