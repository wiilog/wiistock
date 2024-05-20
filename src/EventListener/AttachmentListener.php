<?php


namespace App\EventListener;

use App\Entity\Attachment;
use App\Service\AttachmentService;
use Doctrine\Common\EventSubscriber;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;


class AttachmentListener implements EventSubscriber {

    #[Required]
    public AttachmentService $attachmentService;

    public function getSubscribedEvents(): array {
        return [
            "postRemove",
        ];
    }

    #[AsEventListener(event: "postRemove")]
    public function postRemove(Attachment $attachment): void {
        try {
            $path = $this->attachmentService->getServerPath($attachment);
            if (file_exists($path)) {
                unlink($path);
            }
        }
        catch(Throwable) {}
    }

}
