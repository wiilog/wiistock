<?php


namespace App\Entity\Traits;


use App\Entity\Attachment;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\ManyToMany;

trait AttachmentTrait
{

    /**
     * @ManyToMany(targetEntity="Attachment")
     * @var ArrayCollection $attachments
     */
    private $attachments;

    /**
     * @return Collection|Attachment[]
     */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    public function addAttachment(Attachment $attachment): self
    {
        if (!$this->attachments->contains($attachment)) {
            $this->attachments[] = $attachment;
        }
        return $this;
    }

    public function removeAttachment(Attachment $attachment): self
    {
        if ($this->attachments->contains($attachment)) {
            $this->attachments->removeElement($attachment);
        }
        return $this;
    }

    public function clearAttachments(): self{
        $this->attachments->clear();

        return $this;
    }

    public function removeIfNotIn(array $ids): self{
        foreach ($this->attachments as $attachment) {
            if (!in_array($attachment->getId(), $ids)) {
                $this->attachments->removeElement($attachment);
            }
        }
        return $this;
    }
}
