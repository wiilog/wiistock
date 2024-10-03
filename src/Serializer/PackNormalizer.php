<?php

namespace App\Serializer;

use App\Entity\Pack;
use App\Service\FormatService;
use Exception;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class PackNormalizer implements NormalizerInterface, NormalizerAwareInterface{

    use NormalizerAwareTrait;

    public function __construct(private FormatService $formatService) {}

    public function normalize(mixed $object, string $format = null, array $context = []): array {
        /** @var Pack $pack */
        $pack = $object;

        return match ($context["usage"]) {
            SerializerUsageEnum::MOBILE => $this->normalizeForMobile($pack, $format, $context),
            default => throw new Exception("Invalid usage"),
        };
    }

    public function supportsNormalization(mixed $data, string $format = null): bool {
        return $data instanceof Pack;
    }

    public function normalizeForMobile (Pack $pack, string $format = null, array $context = []): array {
        $lastTracking = $pack->getLastTracking();
        return [
            "code" => $pack->getCode(),
            "quantity" => $pack->getQuantity(),
            "nature" => $this->formatService->nature($pack->getNature(), null),
            "location" => $this->formatService->location($lastTracking?->getEmplacement()),
            "date" => $this->formatService->datetime($lastTracking->getDatetime()),
        ];
    }
}