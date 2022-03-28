<?php

namespace App\Service\Transport;

use App\Entity\Pack;
use App\Entity\Statut;
use App\Entity\Transport\StatusHistory;
use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\Transport\TransportOrder;
use App\Entity\Transport\TransportHistory;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class TransportService {

    public const CREATED = "CREATED";
    public const AFFECTED_ROUND = "AFFECTED_ROUND";
    public const PREPARED_DELIVERY = "PREPARED_DELIVERY";
    public const ONGOING = "ONGOING";
    public const REJECTED_PACK = "REJECTED_PACK";
    public const FINISHED = "FINISHED";
    public const ADD_COMMENT = "ADD_COMMENT";
    public const ADD_ATTACHMENT = "ADD_ATTACHMENT";
    public const FAILED = "FAILED";
    public const PACKS_FAILED = "PACKS_FAILED";
    public const NO_MONITORING = "NO_MONITORING";
    public const SUBCONTRACT_UPDATE = "SUBCONTRACT_UPDATE";
    public const AWAITING_VALIDATION = "AWAITING_VALIDATION";
    public const SUBCONTRACTED = "SUBCONTRACTED";
    public const REJECTED_DELIVERY = "REJECTED_DELIVERY";
    public const CANCELLED = "CANCELLED";

    public const CONTENT = [
        self::CREATED => "{user} a créé la {category}",
        self::AFFECTED_ROUND => "La {category} a été affectée à une tournée",
        self::PREPARED_DELIVERY => "{user} a préparé la livraison",
        self::ONGOING => "{user} a débuté la {category}",
        self::REJECTED_PACK => "{user} a écarté le colis {pack} ({reason})",
        self::FINISHED => "{user} a terminé la {category}",
        self::ADD_COMMENT => "{user} a ajouté un commentaire {comment}",
        self::ADD_ATTACHMENT => "{user} a ajouté une pièce-jointe {attachment}",
        self::FAILED => "{user} n'a pas pu effectuer la {category}",
        self::PACKS_FAILED => "{user} a déposé le colis {pack} sur {location}",
        self::NO_MONITORING => "Le suivi en temps réel n'est pas disponible car la livraison est un horaire non ouvré {message}",
        self::SUBCONTRACT_UPDATE => "{user} a indiqué que la livraison était {status} le {date}",
        self::AWAITING_VALIDATION => "La demande est en attente de validation",
        self::SUBCONTRACTED => "La demande a été sous-traitée",
        self::REJECTED_DELIVERY => "La livraison a été rejetée de la tournée",
        self::CANCELLED => "{user} a annulé la {category}",
    ];

    public EntityManagerInterface $manager;

    public function updateStatus(TransportDeliveryRequest|TransportOrder $transport, Statut $status) {
        $history = (new StatusHistory())
            ->setStatus($status)
            ->setDate(new DateTime());

        $transport->setStatus($status);

        if ($transport instanceof TransportDeliveryRequest) {
            $history->setTransportRequest($transport);
        } else {
            $history->setTransportOrder($transport);
        }

        $this->manager->persist($history);
    }

    public function updateHistory(TransportDeliveryRequest|TransportOrder $transport, string $category, array $params = []) {
        $history = new TransportHistory();
        if($transport instanceof TransportDeliveryRequest) {
            $history->setTransportRequest($transport);
        } else {
            $history->setTransportOrder($transport);
        }

        $history->setCategory($category);

        //TODO

        $this->manager->persist($history);
    }

}