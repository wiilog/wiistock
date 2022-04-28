<?php

namespace App\Service\Transport;

use App\Entity\Attachment;
use App\Entity\Pack;
use App\Entity\Statut;
use App\Entity\Transport\TransportCollectRequest;
use App\Entity\Transport\TransportDeliveryRequest;
use App\Entity\Transport\TransportOrder;
use App\Entity\Transport\TransportHistory;
use App\Entity\Transport\TransportRequest;
use App\Entity\Transport\TransportRound;
use App\Entity\Utilisateur;
use App\Helper\FormatHelper;
use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

class TransportHistoryService {

    public const CATEGORY_TIMELINE = "TIMELINE";
    public const CATEGORY_INFORMATION = "INFORMATION";
    public const CATEGORY_WARNING = "WARNING";
    public const CATEGORY_COMMENT = "COMMENT";
    public const CATEGORY_ATTACHMENT = "ATTACHMENT";

    public const TYPE_REQUEST_CREATION = "CREATED";
    public const TYPE_BOTH_REQUEST_CREATION = "CREATED_BOTH";
    public const TYPE_AFFECTED_ROUND = "AFFECTED_ROUND";
    public const TYPE_CONTACT_VALIDATED = "CONTACT_VALIDATED";
    public const TYPE_LABELS_PRINTING = "PREPARED_DELIVERY";
    public const TYPE_ONGOING = "ONGOING";
    public const TYPE_DROP_REJECTED_PACK = "REJECTED_PACK";
    public const TYPE_FINISHED = "FINISHED";
    public const TYPE_FINISHED_BOTH = "FINISHED_BOTH";
    public const TYPE_ADD_COMMENT = "ADD_COMMENT";
    public const TYPE_ADD_ATTACHMENT = "ADD_ATTACHMENT";
    public const TYPE_FAILED = "FAILED";
    public const TYPE_PACKS_FAILED = "PACKS_FAILED";
    public const TYPE_PACKS_DEPOSITED = "PACKS_DEPOSITED";
    public const TYPE_NO_MONITORING = "NO_MONITORING";
    public const TYPE_SUBCONTRACT_UPDATE = "SUBCONTRACT_UPDATE";
    public const TYPE_AWAITING_VALIDATION = "AWAITING_VALIDATION";
    public const TYPE_SUBCONTRACTED = "SUBCONTRACTED";
    public const TYPE_ACCEPTED = "ACCEPTED";
    public const TYPE_REJECTED_DELIVERY = "REJECTED_DELIVERY";
    public const TYPE_CANCELLED = "CANCELLED";
    public const TYPE_NOT_DELIVERED = "NOT DELIVERED";

    private const CATEGORIES = [
        self::CATEGORY_TIMELINE => [],
        self::CATEGORY_INFORMATION => [],
        self::CATEGORY_WARNING => [],
        self::CATEGORY_COMMENT => [],
        self::CATEGORY_ATTACHMENT => [],
    ];

    public const CONTENT = [
        self::TYPE_REQUEST_CREATION => "{user} a créé la {category}",
        self::TYPE_BOTH_REQUEST_CREATION => "{user} a créé la livraison et une collecte",
        self::TYPE_AFFECTED_ROUND => "{user} a affecté la {category} à la tournée {round} et au livreur {deliverer}",
        self::TYPE_CONTACT_VALIDATED => "{user} a validé la date de collecte avec le patient",
        self::TYPE_LABELS_PRINTING => "{user} a préparé la livraison",
        self::TYPE_ONGOING => "{user} a débuté la {category}",
        self::TYPE_DROP_REJECTED_PACK => "{user} a écarté le colis {pack} ({reason})",
        self::TYPE_FINISHED => "{user} a terminé la {category}",
        self::TYPE_FINISHED_BOTH => "{user} a terminé la livraison et une collecte",
        self::TYPE_ADD_COMMENT => "{user} a laissé un commentaire",
        self::TYPE_ADD_ATTACHMENT => "{user} a ajouté des pièces jointes",
        self::TYPE_FAILED => "{user} n'a pas pu effectuer la {category}",
        self::TYPE_PACKS_FAILED => "{user} a déposé les colis {packs} sur {location}",
        self::TYPE_PACKS_DEPOSITED => "{user} a déposé les objets sur {location}",
        self::TYPE_NO_MONITORING => "Le suivi en temps réel n'est pas disponible car la livraison est un horaire non ouvré. {message}",
        self::TYPE_SUBCONTRACT_UPDATE => "{user} a indiqué que la livraison était {status} le {statusDate}",
        self::TYPE_AWAITING_VALIDATION => "La demande est en attente de validation",
        self::TYPE_SUBCONTRACTED => "La demande a été sous-traitée",
        self::TYPE_ACCEPTED => "La demande a été acceptée",
        self::TYPE_REJECTED_DELIVERY => "La livraison a été rejetée de la tournée",
        self::TYPE_CANCELLED => "{user} a annulé la {category}",
        self::TYPE_NOT_DELIVERED => "La livraison n'a pas était livrée"
    ];

    #[Required]
    public RouterInterface $router;

    #[Required]
    public KernelInterface $kernel;

    public function persistTransportHistory(EntityManagerInterface                $entityManager,
                                            array|TransportRequest|TransportOrder $transports,
                                            string                                $type,
                                            array                                 $params = []): TransportHistory {
        $transports = is_array($transports) ? $transports : [$transports];

        $history = new TransportHistory();
        foreach($transports as $transport) {
            if ($transport instanceof TransportRequest) {
                $history->setRequest($transport);
            }
            else if ($transport instanceof TransportOrder) {
                $history->setOrder($transport);
            }
            else {
                throw new RuntimeException('Unavailable transport type');
            }
        }

        $history
            ->setType($type)
            ->setDate(new DateTime())
            ->setUser($params["user"] ?? null)
            ->setPack($params["pack"] ?? null)
            ->setRound($params["round"] ?? null)
            ->setMessage($params["message"] ?? null)
            ->setDeliverer($params["deliverer"] ?? null)
            ->setReason($params["reason"] ?? null)
            ->setAttachments($params["attachments"] ?? [])
            ->setStatusHistory($params["history"] ?? null)
            ->setLocation($params["location"] ?? null);

        $entityManager->persist($history);

        return $history;
    }

    private function formatEntity(mixed $entity): ?string {
        switch (gettype($entity)) {
            case "object":
                if($entity instanceof Utilisateur) {
                    return "<span class='text-primary font-weight-bold'>{$entity->getUsername()}</span>";
                }
                else if($entity instanceof Pack) {
                    return $entity->getCode();
                }
                else if($entity instanceof TransportRound) {
                    return "<span class='text-primary underlined'>{$entity->getNumber()}</span>";
                }
                else if($entity instanceof DateTime) {
                    return FormatHelper::datetime($entity);
                }
                else if($entity instanceof Statut) {
                    return FormatHelper::status($entity);
                }
                else if($entity instanceof Collection) {
                    if ($entity->get(0) instanceof Attachment) {
                        $formatedValue = Stream::from($entity)->map(function(Attachment $attachment) {
                            $name = $attachment->getOriginalName();
                            $path = $this->kernel->getProjectDir() . '/public/uploads/attachements/' . $attachment->getFileName();

                            return
                                "<div class='attachment-line'>
                                    <img src='$path' alt='$name'>
                                    <span class='text-primary underlined'>$name</span>
                                </div>";
                        })->join(";");
                    } else {
                        return "";
                    }
                }
                else {
                    throw new RuntimeException("Unkown class");
                }
            break;
            case "string":
                if(class_exists($entity)) {
                    return match ($entity) {
                        TransportDeliveryRequest::class => "livraison",
                        TransportCollectRequest::class => "collecte",
                    };
                } else {
                    return $entity;
                }
            case "NULL":
                return null;
            default:
                return $entity;
        }

        return $formatedValue;
    }

    public function formatHistory(TransportHistory $history): string {
        $replace = [
            "{category}" => $this->formatEntity($history->getRequest() ? get_class($history->getRequest()) : get_class($history->getOrder()->getRequest())),
            "{user}" => $this->formatEntity($history->getUser()),
            "{pack}" => $this->formatEntity($history->getPack()),
            "{round}" => $this->formatEntity($history->getRound()),
            "{message}" => $this->formatEntity($history->getMessage()),
            "{deliverer}" => $this->formatEntity($history->getDeliverer()),
            "{reason}" => $this->formatEntity($history->getReason()),
            "{status}" => $this->formatEntity($history->getStatusHistory()?->getStatus()),
            "{statusDate}" => $this->formatEntity($history->getStatusHistory()?->getDate()),
            "{comment}" => $this->formatEntity($history->getComment()),
            "{attachments}" => $this->formatEntity($history->getAttachments())
        ];

        return str_replace(array_keys($replace), array_values($replace), self::CONTENT[$history->getType()]);
    }

    private function getCategoryFromType(string $type): string {
        return match($type) {
            self::TYPE_REQUEST_CREATION, self::TYPE_LABELS_PRINTING, self::TYPE_ONGOING, self::TYPE_FINISHED, self::TYPE_SUBCONTRACT_UPDATE, self::TYPE_AWAITING_VALIDATION, self::TYPE_SUBCONTRACTED, self::TYPE_PACKS_DEPOSITED => self::CATEGORY_TIMELINE,
            self::TYPE_AFFECTED_ROUND, self::TYPE_PACKS_FAILED, self::TYPE_CONTACT_VALIDATED => self::CATEGORY_INFORMATION,
            self::TYPE_DROP_REJECTED_PACK, self::TYPE_FAILED, self::TYPE_NO_MONITORING, self::TYPE_REJECTED_DELIVERY, self::TYPE_CANCELLED => self::CATEGORY_WARNING,
            self::TYPE_ADD_COMMENT => self::CATEGORY_COMMENT,
            self::TYPE_ADD_ATTACHMENT => self::CATEGORY_ATTACHMENT,
            default => throw new RuntimeException("Unknown type")
        };
    }

    public function getIconFromType(string $type): string {
        $category = $this->getCategoryFromType($type);
        return match ($category) {
            self::CATEGORY_ATTACHMENT => 'timeline-attachment.svg',
            self::CATEGORY_COMMENT => 'timeline-comment.svg',
            self::CATEGORY_WARNING => 'timeline-urgent.svg',
            self::CATEGORY_INFORMATION => 'timeline-information.svg',
            default => 'timeline.svg', // CATEGORY_TIMELINE
        };
    }

}
