<?php

namespace App\Serializer;

use App\Entity\Article;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Helper\FormatHelper;
use App\Service\FormatService;
use App\Service\PackService;
use App\Service\TrackingMovementService;
use Exception;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use WiiCommon\Helper\Stream;

class TrackingMovementNormalizer implements NormalizerInterface, NormalizerAwareInterface{

    use NormalizerAwareTrait;

    private const SUPPORTED_USAGES = [
        SerializerUsageEnum::MOBILE_READING_MENU,
        SerializerUsageEnum::MOBILE_DROP_MENU,
        SerializerUsageEnum::CSV_EXPORT,
    ];

    public function __construct(
        private FormatService           $formatService,
        private PackService             $packService,
        private TrackingMovementService $trackingMovementService
    ) {}

    public function normalize(mixed $object, string $format = null, array $context = []): array {
        /** @var TrackingMovement $trackingMovement */
        $trackingMovement = $object;

        /** @var SerializerUsageEnum $usage */
        $usage = $context["usage"] ?? null;

        $usageStr = $usage ? $usage->value : "null";
        $supportedUsageStr = Stream::from(self::SUPPORTED_USAGES)
            ->map(static fn(SerializerUsageEnum $supported) => $supported->value)
            ->join(", ");

        return match ($usage) {
            SerializerUsageEnum::MOBILE_READING_MENU => $this->normalizeForMobileReadingPage($trackingMovement, $format, $context),
            SerializerUsageEnum::MOBILE_DROP_MENU => $this->normalizeForMobileTrackingPage($trackingMovement, $format, $context),
            SerializerUsageEnum::CSV_EXPORT => $this->normalizeForCsvExport($trackingMovement, $format, $context),
            default => throw new Exception("Invalid usage {$usageStr}, should be one of {$supportedUsageStr}"),
        };
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool{
        return $data instanceof TrackingMovement;
    }

    public function getSupportedTypes(?string $format): array {
        return [
            TrackingMovement::class => true,
        ];
    }

    public function normalizeForMobileReadingPage(TrackingMovement $trackingMovement, string $format = null, array $context = []): array {
        return [
            "type" => ucfirst($this->formatService->status($trackingMovement->getType())),
            "date" => $this->formatService->datetime($trackingMovement->getDatetime()),
            "location" => $this->formatService->location($trackingMovement->getEmplacement()),
            "nature" => $this->formatService->nature($trackingMovement->getOldNature()),
            "operator" => $this->formatService->user($trackingMovement->getOperateur()),
            "comment" => $this->formatService->html($trackingMovement->getCommentaire()),
            "quantity" => $trackingMovement->getQuantity(),
        ];
    }

    public function normalizeForMobileTrackingPage(TrackingMovement $trackingMovement, string $format = null, array $context = []): array {
        $pack = $trackingMovement->getPack();
        $includeMovementId = $context["includeMovementId"] ?? false;

        $res = [
            ...($includeMovementId
                ? ["id" => $trackingMovement->getId()]
                : []),
            "type" => $trackingMovement->getType()?->getCode(),
            "date" => $trackingMovement->getUniqueIdForMobile(),
            "ref_emplacement" => $this->formatService->location($trackingMovement->getEmplacement()),
            "nature_id" => $trackingMovement->getPack()?->getNature()?->getId(),
            "operateur" => $this->formatService->user($trackingMovement->getOperateur()),
            "comment" => $this->formatService->html($trackingMovement->getCommentaire()),
            "quantity" => $trackingMovement->getQuantity(),
            "ref_article" => $this->formatService->pack($pack),
            "finished" => $trackingMovement->isFinished(),
            "fromStock" => !empty($trackingMovement->getMouvementStock()) || $pack->isArticleContainer(),
            "isGroup" => $pack->isGroup(),
            "packGroup" => $trackingMovement->getPackGroup()?->getCode(),
            "articles" => Stream::from($pack->getChildArticles())
                ->map(static fn(Article $article) => $article->getBarCode())
                ->join(";"),
        ];

        $trackingPack = $trackingMovement->getPack();

        if ($trackingPack->isGroup()) {
            $subPacks = $trackingPack->getContent()
                ->map(fn(Pack $pack) => ([
                    "code" => $pack->getCode(),
                    "ref_article" => $pack->getCode(),
                    "nature_id" => $pack->getNature()?->getId(),
                    "quantity" => $pack->getLastAction()
                        ? $pack->getLastAction()->getQuantity()
                        : 1,
                    "type" => $pack->getLastAction()?->getType()?->getCode(),
                    "ref_emplacement" => $pack->getLastAction()?->getEmplacement()?->getLabel(),
                    "date" => $this->formatService->datetime($pack->getLastAction()?->getDatetime()),
                ]))
                ->toArray();
        }

        $res['subPacks'] = $subPacks ?? [];


        $trackingDelayData = $this->packService->formatTrackingDelayData($trackingPack);

        $res["trackingDelay"] = $trackingDelayData["delayHTML"] ?? null;
        $res["trackingDelayColor"] = $trackingDelayData["color"] ?? null;
        $res["limitTreatmentDate"] = $trackingDelayData["dateHTML"] ?? null;

        return $res;
    }

    public function normalizeForCsvExport(TrackingMovement $trackingMovement, string $format = null, array $context = []): array {
        $fromData = $this->trackingMovementService->getFromColumnData($trackingMovement);
        $fromLabel = $fromData["fromLabel"] ?? "";
        $fromNumber = $fromData["from"] ?? "";
        $from = trim("$fromLabel $fromNumber") ?: null;
        $pack = $trackingMovement->getPack();
        $arrival = $pack?->getArrivage();
        return [
            "date" => $this->formatService->datetime($trackingMovement->getDatetime()),
            "logisticUnit" => $this->formatService->pack($pack),
            "location" => $this->formatService->location($trackingMovement->getEmplacement()),
            "quantity" => $trackingMovement->getQuantity(),
            "type" => ucfirst($this->formatService->status($trackingMovement->getType())),
            "operator" => $this->formatService->user($trackingMovement->getOperateur()),
            "comment" => $this->formatService->html($trackingMovement->getCommentaire()),
            "hasAttachments" => $this->formatService->bool($trackingMovement->getAttachments()->count() > 0),
            "from" => $from,
            "arrivalOrderNumber" => $arrival?->getNumeroCommandeList(),
            "isUrgent" => $this->formatService->bool($arrival?->getIsUrgent(), false),
            "nature" => $this->formatService->nature($trackingMovement->getOldNature()),
            "packGroup" => $this->formatService->pack($trackingMovement->getPackGroup()),
            "freeFields" => $trackingMovement->getFreeFields(),
        ];
    }
}
