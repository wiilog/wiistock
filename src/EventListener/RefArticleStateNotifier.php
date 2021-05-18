<?php

namespace App\EventListener;

use App\Entity\Article;
use App\Entity\PurchaseRequest;
use App\Entity\Reception;
use Exception;

class RefArticleStateNotifier {

    public function postUpdate($entity) {
        // TODO
        if ($entity instanceof Reception) {

        }
        else if ($entity instanceof PurchaseRequest) {

        }
    }

    public function postPersist($entity) {
        // TODO
        if ($entity instanceof Reception) {

        }
        else if ($entity instanceof PurchaseRequest) {

        }
    }

    public function postRemove($entity) {
        // TODO
        if ($entity instanceof Reception) {

        }
        else if ($entity instanceof PurchaseRequest) {

        }
    }
}
