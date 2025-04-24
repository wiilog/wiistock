<?php


namespace App\EventListener;

use App\Entity\Attachment;
use App\Service\AttachmentService;
use Doctrine\Common\EventSubscriber;
use Doctrine\Persistence\Proxy;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Throwable;


class AttachmentListener implements EventSubscriber {

    public function __construct(
        private AttachmentService $attachmentService,
    ) {
    }

    public function getSubscribedEvents(): array {
        return [
            "preRemove",
            "postRemove",
        ];
    }

    #[AsEventListener(event: "preRemove")]
    public function preRemove(Attachment $attachment): void {
        // if it's a lazy entity we preload it before postRemove
        // in postRemove action the row in db is deleted, and we can't retrieve the path of the file for this attachment
        if ($attachment instanceof Proxy) {
            $attachment->__load();
        }
    }

    #[AsEventListener(event: "postRemove")]
    public function postRemove(Attachment $attachment): void {
        try {
            $path = $this->attachmentService->getServerPath($attachment);
            if (file_exists($path)) {
                unlink($path);
            }
        }
        catch(Throwable $exception) {
            if (!in_array($_ENV['APP_ENV'], ['prod', 'preprod'])) {
                throw $exception;
            }
        }
    }

}
