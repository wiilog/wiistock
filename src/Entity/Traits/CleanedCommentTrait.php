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
        $cleanedComment = $comment !== null ? strip_tags($comment) : null;
        $this->cleanedComment = $cleanedComment !== "" ? $cleanedComment : null;

        return $this;
    }

}
