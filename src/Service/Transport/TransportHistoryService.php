<?php

namespace App\Service\Transport;

use App\Entity\Attachment;
use App\Entity\Emplacement;
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
    public const TYPE_REQUEST_AFFECTED_ROUND = "REQUEST_AFFECTED_ROUND";
    public const TYPE_CONTACT_VALIDATED = "CONTACT_VALIDATED";
    public const TYPE_LABELS_PRINTING = "PREPARED_DELIVERY";
    public const TYPE_ONGOING = "ONGOING";
    public const TYPE_DROP_REJECTED_PACK = "REJECTED_PACK";
    public const TYPE_FINISHED = "FINISHED";
    public const TYPE_FINISHED_BOTH = "FINISHED_BOTH";
    public const TYPE_ADD_COMMENT = "ADD_COMMENT";
    public const TYPE_ADD_ATTACHMENT = "ADD_ATTACHMENT";
    public const TYPE_FAILED_DELIVERY = "FAILED";
    public const TYPE_FAILED_COLLECT = "TYPE_FAILED_COLLECT";
    public const TYPE_PACKS_FAILED = "PACKS_FAILED";
    public const TYPE_PACKS_DEPOSITED = "PACKS_DEPOSITED";
    public const TYPE_NO_MONITORING = "NO_MONITORING";
    public const TYPE_SUBCONTRACT_UPDATE = "SUBCONTRACT_UPDATE";
    public const TYPE_AWAITING_VALIDATION = "AWAITING_VALIDATION";
    public const TYPE_AWAITING_PLANNING = "AWAITING_PLANNING";
    public const TYPE_SUBCONTRACTED = "SUBCONTRACTED";
    public const TYPE_ACCEPTED = "ACCEPTED";
    public const TYPE_REJECTED_DELIVERY = "REJECTED_DELIVERY";
    public const TYPE_CANCELLED = "CANCELLED";
    public const TYPE_REQUEST_EDITED = "REQUEST_EDITED";

    public const CONTENT = [
        self::TYPE_REQUEST_CREATION => "{user} a créé la {category}",
        self::TYPE_BOTH_REQUEST_CREATION => "{user} a créé la livraison et une collecte",
        self::TYPE_AFFECTED_ROUND => "{user} a affecté la {category} à la tournée {round} et au livreur {deliverer}",
        self::TYPE_REQUEST_AFFECTED_ROUND => "La {category} a été affectée sur une tournée",
        self::TYPE_CONTACT_VALIDATED => "{user} a validé la date de collecte avec le patient",
        self::TYPE_LABELS_PRINTING => "{user} a préparé la livraison",
        self::TYPE_ONGOING => "{user} a débuté la {category}",
        self::TYPE_DROP_REJECTED_PACK => "{user} a écarté le colis {pack}",
        self::TYPE_FINISHED => "{user} a terminé la {category}",
        self::TYPE_FINISHED_BOTH => "{user} a terminé la livraison et une collecte",
        self::TYPE_ADD_COMMENT => "{user} a laissé un commentaire",
        self::TYPE_ADD_ATTACHMENT => "{user} a ajouté des pièces jointes",
        self::TYPE_FAILED_DELIVERY => "{user} n'a pas pu effectuer la livraison",
        self::TYPE_FAILED_COLLECT => "{user} n'a pas pu effectuer la collecte",
        self::TYPE_PACKS_FAILED => "{user} a déposé les colis {message} sur {location}",
        self::TYPE_PACKS_DEPOSITED => "{user} a déposé les objets sur {location}",
        self::TYPE_NO_MONITORING => "Le suivi en temps réel n'est pas disponible car la livraison est un horaire non ouvré. {message}",
        self::TYPE_SUBCONTRACT_UPDATE => "{user} a indiqué que la livraison était {status} le {statusDate}",
        self::TYPE_AWAITING_VALIDATION => "La demande est en attente de validation",
        self::TYPE_AWAITING_PLANNING => "La demande est en attente de planification",
        self::TYPE_SUBCONTRACTED => "La demande a été sous-traitée",
        self::TYPE_ACCEPTED => "La demande a été acceptée",
        self::TYPE_REJECTED_DELIVERY => "La livraison a été rejetée de la tournée",
        self::TYPE_CANCELLED => "{user} a annulé la {category}",
        self::TYPE_REQUEST_EDITED => "La demande a été modifiée"
    ];

    #[Required]
    public RouterInterface $router;

    #[Required]
    public KernelInterface $kernel;

    public function persistTransportHistory(EntityManagerInterface                $entityManager,
                                            array|TransportRequest|TransportOrder|TransportRound $transports,
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
            else if ($transport instanceof TransportRound) {
                $history->setRound($transport);
            }
            else {
                throw new RuntimeException('Unavailable transport type');
            }
        }

        $history
            ->setType($type)
            ->setDate($params["date"] ?? new DateTime())
            ->setStatusDate($params["statusDate"] ?? new DateTime())
            ->setUser($params["user"] ?? null)
            ->setPack($params["pack"] ?? null)
            ->setRound($params["round"] ?? null)
            ->setMessage($params["message"] ?? null)
            ->setDeliverer($params["deliverer"] ?? null)
            ->setReason($params["reason"] ?? null)
            ->setAttachments($params["attachments"] ?? [])
            ->setComment(StringHelper::cleanedComment($params["comment"]) ?? null)
            ->setStatusHistory($params["history"] ?? null)
            ->setLocation($params["location"] ?? null);

        $entityManager->persist($history);

        return $history;
    }

    private function formatEntity(mixed $entity, bool $highlighted = true): ?string {
        switch (gettype($entity)) {
            case "object":
                if($entity instanceof Utilisateur) {
                    $highlightClasses = $highlighted ? 'text-primary font-weight-bold' : '';

                    return "<span class='$highlightClasses'>{$entity->getUsername()}</span>";
                }
                else if($entity instanceof Pack) {
                    return $entity->getCode();
                }
                else if($entity instanceof Emplacement) {
                    return $entity->getLabel();
                }
                else if($entity instanceof TransportRound) {
                    $numberPrefix = TransportRound::NUMBER_PREFIX;
                    $url = $this->router->generate('transport_round_show', [
                        'transportRound' => $entity->getId()
                    ]);
                    return "<a class='text-primary underlined' href='$url'>$numberPrefix{$entity->getNumber()}</a>";
                }
                else if($entity instanceof DateTime) {
                    $date = FormatHelper::longDate($entity, ['time' => true, 'year' => true]);
                    return "<span class='font-weight-bold'>{$date}</span>";
                }
                else if($entity instanceof Statut) {
                    $status = FormatHelper::status($entity);
                    return "<span class='font-weight-bold'>{$status}</span>";
                }
                else if($entity instanceof Collection) {
                    if ($entity->get(0) instanceof Attachment) {
                        $formatedValue = Stream::from($entity)->map(function(Attachment $attachment) {
                            $name = $attachment->getOriginalName();
                            $publicUrl = $attachment->getFullPath();
                            $imagePath = $this->kernel->getProjectDir() . '/public' . $publicUrl;

                            $imagesize = getimagesize($imagePath);
                            $imageType = $imagesize[2] ?? null;
                            $isImage = in_array($imageType, [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_BMP]);

                            return
                                "<div class='attachment-line mt-2'>" .
                                    ($isImage ? "<img src='$publicUrl' alt='$name'>" : '') .
                                    "<a class='text-primary underlined pointer'
                                        download='$name'
                                        href='$publicUrl'>$name</a>
                                </div>";
                        })->join("");
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
            "{deliverer}" => $this->formatEntity($history->getDeliverer(), false),
            "{reason}" => $this->formatEntity($history->getReason()),
            "{location}" => $this->formatEntity($history->getLocation()),
            "{status}" => $this->formatEntity($history->getStatusHistory()?->getStatus()),
            "{statusDate}" => $history->getStatusDate()
                ? $this->formatEntity($history->getStatusDate())
                : $this->formatEntity($history->getStatusHistory()?->getDate()),
        ];

        return str_replace(array_keys($replace), array_values($replace), self::CONTENT[$history->getType()]);
    }

    private function getCategoryFromType(mixed $entity, string $type): string {
        if($type === self::TYPE_LABELS_PRINTING) {
            if($entity instanceof TransportRequest) {
                return self::CATEGORY_TIMELINE;
            } else {
                return self::CATEGORY_INFORMATION;
            }
        }

        return match($type) {
            self::TYPE_BOTH_REQUEST_CREATION,
            self::TYPE_ONGOING,
            self::TYPE_FINISHED,
            self::TYPE_FINISHED_BOTH,
            self::TYPE_SUBCONTRACT_UPDATE,
            self::TYPE_AWAITING_VALIDATION,
            self::TYPE_SUBCONTRACTED,
            self::TYPE_ACCEPTED,
            self::TYPE_AWAITING_PLANNING,
            self::TYPE_PACKS_DEPOSITED,
            self::TYPE_AFFECTED_ROUND,
            self::TYPE_CONTACT_VALIDATED =>  self::CATEGORY_TIMELINE,

            self::TYPE_REQUEST_CREATION,
            self::TYPE_LABELS_PRINTING,
            self::TYPE_REQUEST_EDITED,
            self::TYPE_REQUEST_AFFECTED_ROUND,
            self::TYPE_PACKS_FAILED => self::CATEGORY_INFORMATION,

            self::TYPE_DROP_REJECTED_PACK,
            self::TYPE_FAILED_DELIVERY,
            self::TYPE_FAILED_COLLECT,
            self::TYPE_NO_MONITORING,
            self::TYPE_REJECTED_DELIVERY,
            self::TYPE_CANCELLED => self::CATEGORY_WARNING,

            self::TYPE_ADD_COMMENT => self::CATEGORY_COMMENT,

            self::TYPE_ADD_ATTACHMENT => self::CATEGORY_ATTACHMENT,

            default => throw new RuntimeException("Unknown type")
        };
    }

    public function getIconFromType(mixed $entity, string $type): string {
        $category = $this->getCategoryFromType($entity, $type);
        return match ($category) {
            self::CATEGORY_ATTACHMENT => 'timeline-attachment.svg',
            self::CATEGORY_COMMENT => 'timeline-comment.svg',
            self::CATEGORY_WARNING => 'timeline-urgent.svg',
            self::CATEGORY_INFORMATION => 'timeline-information.svg',
            default => 'timeline.svg', // CATEGORY_TIMELINE
        };
    }

}
