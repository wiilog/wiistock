<?php

namespace App\Serializer;

use App\Entity\Article;
use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Helper\FormatHelper;
use App\Service\FormatService;
use App\Service\PackService;
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
    ];

    public function __construct(
        private FormatService $formatService,
        private PackService   $packService,
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

        $res["trackingDelay"] = $trackingDelayData["delay"] ?? null;
        $res["trackingDelayColor"] = $trackingDelayData["color"] ?? null;
        $res["limitTreatmentDate"] = $this->formatService->datetime($trackingPack->getTrackingDelay()?->getLimitTreatmentDate(), null);

        return $res;
    }
}
