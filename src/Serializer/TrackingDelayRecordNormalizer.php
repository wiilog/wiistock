<?php

namespace App\Serializer;

use App\Entity\Tracking\TrackingDelayRecord;
use App\Service\DateTimeService;
use App\Service\FormatService;
use Exception;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use WiiCommon\Helper\Stream;

class TrackingDelayRecordNormalizer implements NormalizerInterface, NormalizerAwareInterface{

    use NormalizerAwareTrait;

    private const SUPPORTED_USAGES = [
        SerializerUsageEnum::PACK_SHOW,
    ];

    public function __construct(
        private FormatService   $formatService,
        private DateTimeService $dateTimeService,
    ) {}

    public function normalize(mixed $object, string $format = null, array $context = []): array {
        /** @var TrackingDelayRecord $record */
        $record = $object;

        /** @var SerializerUsageEnum $usage */
        $usage = $context["usage"] ?? null;

        $usageStr = $usage ? $usage->value : "null";
        $supportedUsageStr = Stream::from(self::SUPPORTED_USAGES)
            ->map(static fn(SerializerUsageEnum $supported) => $supported->value)
            ->join(", ");

        return match ($usage) {
            SerializerUsageEnum::PACK_SHOW => $this->normalizeForPackShow($record, $format, $context),
            default => throw new Exception("Invalid usage {$usageStr}, should be one of {$supportedUsageStr}"),
        };
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool{
        return $data instanceof TrackingDelayRecord;
    }

    public function getSupportedTypes(?string $format): array {
        return [
            TrackingDelayRecord::class => true,
        ];
    }

    public function normalizeForPackShow(TrackingDelayRecord $record, string $format = null, array $context = []): array {
        $remainingDelaySeconds = $record->getRemainingTrackingDelay();

        if (isset($remainingDelaySeconds)) {
            $remainingDelayInterval = $this->dateTimeService->convertSecondsToDateInterval($remainingDelaySeconds);
            $remainingDelayInterval->invert = $remainingDelaySeconds < 0;
        }

        return [
            "date" => $this->formatService->datetime($record->getDate()),
            "type" => $this->formatService->status($record->getType()),
            "event" => $record->getTrackingEvent()?->name,
            "location" => $this->formatService->location($record->getLocation()),
            "newNature" => $this->formatService->nature($record->getNewNature()),
            "remainingDelay" => $this->formatService->delay($remainingDelayInterval ?? null),
        ];
    }
}
