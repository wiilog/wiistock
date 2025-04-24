<?php


namespace App\EventListener\Doctrine;

use App\Entity\Attachment;
use App\Service\AttachmentService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Proxy;
use Throwable;


#[AsEntityListener(event: Events::preRemove, method: 'preRemove', lazy: true, entity: Attachment::class)]
#[AsEntityListener(event: Events::postRemove, method: 'postRemove', lazy: true, entity: Attachment::class)]
class AttachmentListener {

    public function __construct(
        private AttachmentService $attachmentService,
    ) {
    }

    public function preRemove(Attachment $attachment): void {
        // if it's a lazy entity we preload it before postRemove
        // in postRemove action the row in db is deleted, and we can't retrieve the path of the file for this attachment
        if ($attachment instanceof Proxy) {
            $attachment->__load();
        }
    }

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
