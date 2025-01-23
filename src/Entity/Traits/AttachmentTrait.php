<?php


namespace App\Entity\Traits;


use App\Entity\Attachment;
use App\Entity\Tracking\TrackingMovement;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\ManyToMany;

trait AttachmentTrait {

    /**
     * @var Collection<Attachment>
     */
    #[ManyToMany(targetEntity: Attachment::class, cascade: ['persist', 'remove'])]
    private Collection $attachments;

    /**
     * @return Collection<Attachment>
     */
    public function getAttachments(): Collection {
        return $this->attachments;
    }

    public function addAttachment(Attachment $attachment): self {
        if(!$this->attachments->contains($attachment)) {
            $this->attachments[] = $attachment;
        }
        return $this;
    }

    public function removeAttachment(Attachment $attachment): self {
        if($this->attachments->contains($attachment)) {
            $this->attachments->removeElement($attachment);
        }
        return $this;
    }

    /**
     * @param iterable<Attachment> $attachments
     */
    public function setAttachments(iterable $attachments): self {
        foreach($attachments as $attachment) {
            $this->addAttachment($attachment);
        }

        return $this;
    }

    public function clearAttachments(): self {
        $this->attachments->clear();

        return $this;
    }

}
