<?php

namespace App\Service\Transport;

use App\Entity\Pack;
use App\Entity\Statut;
use App\Entity\Transport\StatusHistory;
use App\Entity\Transport\TransportCollectRequest;
use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\Transport\TransportOrder;
use App\Entity\Transport\TransportHistory;
use App\Entity\Transport\TransportRound;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

class TransportService {

    public const TIMELINE = "TIMELINE";
    public const INFORMATION = "INFORMATION";
    public const WARNING = "WARNING";
    public const COMMENT = "COMMENT";
    public const ATTACHMENT = "ATTACHMENT";

    public const CREATED = "CREATED";
    public const CREATED_BOTH = "CREATED_BOTH";
    public const AFFECTED_ROUND = "AFFECTED_ROUND";
    public const CONTACT_VALIDATED = "CONTACT_VALIDATED";
    public const PREPARED_DELIVERY = "PREPARED_DELIVERY";
    public const ONGOING = "ONGOING";
    public const REJECTED_PACK = "REJECTED_PACK";
    public const FINISHED = "FINISHED";
    public const FINISHED_BOTH = "FINISHED_BOTH";
    public const ADD_COMMENT = "ADD_COMMENT";
    public const ADD_ATTACHMENT = "ADD_ATTACHMENT";
    public const FAILED = "FAILED";
    public const PACKS_FAILED = "PACKS_FAILED";
    public const PACKS_DEPOSITED = "PACKS_DEPOSITED";
    public const NO_MONITORING = "NO_MONITORING";
    public const SUBCONTRACT_UPDATE = "SUBCONTRACT_UPDATE";
    public const AWAITING_VALIDATION = "AWAITING_VALIDATION";
    public const SUBCONTRACTED = "SUBCONTRACTED";
    public const REJECTED_DELIVERY = "REJECTED_DELIVERY";
    public const CANCELLED = "CANCELLED";

    private const CATEGORY = [
        self::TIMELINE => [],
        self::INFORMATION => [],
        self::WARNING => [],
        self::COMMENT => [],
        self::ATTACHMENT => [],
    ];

    public const CONTENT = [
        self::CREATED => "{user} a créé la {category}",
        self::CREATED_BOTH => "{user} a créé la livraison et une collecte",
        self::AFFECTED_ROUND => "{user} a affecté la {category} à la tournée {round} et au livreur {deliverer}",
        self::CONTACT_VALIDATED => "{user} a validé la date de collecte avec le patient",
        self::PREPARED_DELIVERY => "{user} a préparé la livraison",
        self::ONGOING => "{user} a débuté la {category}",
        self::REJECTED_PACK => "{user} a écarté le colis {pack} ({reason})",
        self::FINISHED => "{user} a terminé la {category}",
        self::FINISHED_BOTH => "{user} a terminé la livraison et une collecte",
        self::ADD_COMMENT => "{user} a ajouté un commentaire {comment}",
        self::ADD_ATTACHMENT => "{user} a ajouté une pièce-jointe {attachment}",
        self::FAILED => "{user} n'a pas pu effectuer la {category}",
        self::PACKS_FAILED => "{user} a déposé le colis {pack} sur {location}",
        self::PACKS_DEPOSITED => "{user} a déposé les objets sur {location}",
        self::NO_MONITORING => "Le suivi en temps réel n'est pas disponible car la livraison est un horaire non ouvré {message}",
        self::SUBCONTRACT_UPDATE => "{user} a indiqué que la livraison était {status} le {statusDate}",
        self::AWAITING_VALIDATION => "La demande est en attente de validation",
        self::SUBCONTRACTED => "La demande a été sous-traitée",
        self::REJECTED_DELIVERY => "La livraison a été rejetée de la tournée",
        self::CANCELLED => "{user} a annulé la {category}",
    ];

    #[Required]
    public EntityManagerInterface $manager;

    #[Required]
    public RouterInterface $router;

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

    public function updateHistory(array|TransportDeliveryRequest|TransportOrder $transports, string $category, array $params = []) {
        $transports = is_array($transports) ? $transports : [$transports];

        $history = new TransportHistory();
        foreach($transports as $transport) {
            if ($transport instanceof TransportDeliveryRequest) {
                $history->setTransportRequest($transport);
            } else {
                $history->setTransportOrder($transport);
            }
        }

        $history->setType($category)
            ->setDate(new DateTime())
            ->setUser($params["user"] ?? null)
            ->setPack($params["pack"] ?? null)
            ->setRound($params["round"] ?? null)
            ->setDeliverer($params["deliverer"] ?? null)
            ->setReason($params["reason"] ?? null)
            ->setAttachment($params["attachment"] ?? null)
            ->setStatusHistory($params["history"] ?? null)
            ->setLocation($params["location"] ?? null);

        $this->manager->persist($history);
    }

    private function formatEntity(mixed $entity): ?string {
        //TODO: remplacer les ??? par les bonnes classes pour la WIIS-6401
        return match (gettype($entity)) {
            "object" => match (get_class($entity)) {
                Utilisateur::class => "<span class='???'>{$entity->getUsername()}</span>",
                Pack::class => $entity->getCode(),
                TransportRound::class => "<span class='???'>{$entity->getNumber()}</span>",
                DateTime::class => $entity->format("d/m/Y H:i"),
            },
            "string" => class_exists($entity) ? match ($entity) {
                TransportDeliveryRequest::class => "livraison",
                TransportCollectRequest::class => "collect",
            } : $entity,
            "NULL", "unknown type" => null,
            default => $entity,
        };
    }

    private function formatHistory(TransportHistory $history): string {
        $replace = [
            "{category}" => $this->formatEntity(get_class($history->getTransportRequest())),
            "{user}" => $this->formatEntity($history->getUser()),
            "{pack}" => $this->formatEntity($history->getPack()),
            "{round}" => $this->formatEntity($history->getRound()),
            "{deliverer}" => $this->formatEntity($history->getDeliverer()),
            "{reason}" => $this->formatEntity($history->getReason()),
            "{status}" => $this->formatEntity($history->getStatusHistory()->getStatus()),
            "{statusDate}" => $this->formatEntity($history->getStatusHistory()->getDate()),
            "{comment}" => $this->formatEntity($history->getComment()),
        ];

        return str_replace(array_keys($replace), array_values($replace), self::CONTENT[$history->getType()]);
    }

    public function retrieveHistory(TransportDeliveryRequest|TransportOrder $transport): array {
        return Stream::from($transport->getHistory())
            ->map(fn(TransportHistory $history) => [
                "type" => self::CATEGORY[$history->getType()],
                "text" => $this->formatHistory($history),
                "date" => FormatHelper::longDate($history->getDate(), false, true),
            ])
            ->toArray();
    }

}