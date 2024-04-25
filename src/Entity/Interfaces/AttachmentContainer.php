<?php

namespace App\Entity\Interfaces;

use App\Entity\Attachment;
use Doctrine\Common\Collections\Collection;

interface AttachmentContainer
{

    public function getAttachments(): Collection;

    public function addAttachment(Attachment $attachment): self;

    public function removeAttachment(Attachment $attachment): self;

    public function setAttachments($attachments): self;

    public function clearAttachments(): self;

    public function removeIfNotIn(array $ids): self;

}
