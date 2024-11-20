<?php

namespace App\Serializer;

use App\Entity\Pack;
use App\Service\FormatService;
use Exception;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use WiiCommon\Helper\Stream;

class PackNormalizer implements NormalizerInterface, NormalizerAwareInterface{

    use NormalizerAwareTrait;

    private const SUPPORTED_USAGES = [
        SerializerUsageEnum::MOBILE,
    ];

    public function __construct(private FormatService $formatService) {}

    public function normalize(mixed $object, string $format = null, array $context = []): array {
        /** @var Pack $pack */
        $pack = $object;

        /** @var SerializerUsageEnum $usage */
        $usage = $context["usage"] ?? null;

        $usageStr = $usage ? $usage->value : "null";
        $supportedUsageStr = Stream::from(self::SUPPORTED_USAGES)
            ->map(static fn(SerializerUsageEnum $supported) => $supported->value)
            ->join(", ");

        return match ($usage) {
            SerializerUsageEnum::MOBILE => $this->normalizeForMobile($pack, $format, $context),
            default => throw new Exception("Invalid usage {$usageStr}, should be one of {$supportedUsageStr}"),
        };
    }

    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool {
        return $data instanceof Pack;
    }

    public function getSupportedTypes(?string $format): array {
        return [
            Pack::class => true,
        ];
    }

    public function normalizeForMobile (Pack $pack, string $format = null, array $context = []): array {
        $lastAction = $pack->getLastAction();
        return [
            "code" => $pack->getCode(),
            "quantity" => $pack->getQuantity(),
            "nature" => $this->formatService->nature($pack->getNature(), null),
            "location" => $this->formatService->location($lastAction?->getEmplacement()),
            "date" => $this->formatService->datetime($lastAction?->getDatetime()),
        ];
    }
}
