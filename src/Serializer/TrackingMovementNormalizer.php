<?php

namespace App\Serializer;

use App\Entity\Tracking\TrackingMovement;
use App\Service\FormatService;
use Exception;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class TrackingMovementNormalizer implements NormalizerInterface, NormalizerAwareInterface{

    use NormalizerAwareTrait;

    public function __construct(private FormatService $formatService) {}

    public function normalize(mixed $object, string $format = null, array $context = []): array {
        /** @var TrackingMovement $trackingMovement */
        $trackingMovement = $object;

        return match ($context["usage"]) {
            SerializerUsageEnum::MOBILE => $this->normalizeForMobile($trackingMovement, $format, $context),
            default => throw new Exception("Invalid usage"),
        };
    }

    public function supportsNormalization(mixed $data, ?string $format = null): bool{
        return $data instanceof TrackingMovement;
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
}
