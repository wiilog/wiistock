<?php

namespace App\Serializer;

use App\Entity\Article;
use App\Entity\Tracking\TrackingMovement;
use App\Service\FormatService;
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

    public function __construct(private FormatService $formatService) {}

    public function normalize(mixed $object, string $format = null, array $context = []): array {
        /** @var TrackingMovement $trackingMovement */
        $trackingMovement = $object;

        return match ($context["usage"]) {
            SerializerUsageEnum::MOBILE_READING_MENU => $this->normalizeForMobile($trackingMovement, $format, $context),
            SerializerUsageEnum::MOBILE_DROP_MENU => $this->normalizeForMobilePicking($trackingMovement, $format, $context),
            default => throw new Exception("Invalid usage"),
        };
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool{
        return $data instanceof TrackingMovement && in_array($context["usage"] ?? null, self::SUPPORTED_USAGES);
    }

    public function getSupportedTypes(?string $format): array {
        return [
            TrackingMovement::class => true,
        ];
    }

    public function normalizeForMobile (TrackingMovement $trackingMovement, string $format = null, array $context = []): array {
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

    public function normalizeForMobilePicking (TrackingMovement $trackingMovement, string $format = null, array $context = []): array {
        $pack = $trackingMovement->getPack();

        return [
            ...($context["includeMovementId"]
                ? ["id" => $trackingMovement->getId()]
                : []),
            "type" => ucfirst($this->formatService->status($trackingMovement->getType())),
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
            "packParent" => $trackingMovement->getPackParent()?->getCode(),
            "articles" => Stream::from($pack->getChildArticles())
                ->map(static fn(Article $article) => $article->getBarCode())
                ->join(";"),
        ];
    }
}
