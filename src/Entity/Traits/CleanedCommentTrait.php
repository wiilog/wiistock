<?php


namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;

trait CleanedCommentTrait {

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $cleanedComment = null;

    public function getCleanedComment(): ?string {
        return $this->cleanedComment;
    }

    public function setCleanedComment(?string $comment): self {
        $this->cleanedComment = strip_tags($comment);

        return $this;
    }

}
