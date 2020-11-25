<?php


namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;

trait CommentTrait {

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $cleanedComment;

    public function getCleanedComment(): ?string
    {
        return $this->cleanedComment;
    }

    public function setCleanedComment(?string $comment): self
    {
        $this->cleanedComment = strip_tags($comment);

        return $this;
    }
}
