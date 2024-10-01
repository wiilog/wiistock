<?php

namespace App\Serializer;

use App\Entity\Dispatch;
use App\Entity\Fields\SubLineFixedField;
use App\Entity\Pack;
use App\Service\FormatService;
use DateTimeInterface;
use Exception;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class PackNormalizer implements NormalizerInterface, NormalizerAwareInterface{

    use NormalizerAwareTrait;

    public function __construct(private FormatService $formatService) {}

    public function normalize(mixed $object, string $format = null, array $context = []): array {
        /** @var Pack $dispatch */
        $pack = $object;

        switch ($context["usage"]) {
            case SerializerUsageEnum::EXPORT_DISPATCH:
                return $this->normalizeForExportDispatch($pack, $format, $context);
            default:
                throw new Exception("Invalid usage");
        }
    }

    public function supportsNormalization(mixed $data, string $format = null): bool {
        return $data instanceof Pack;
    }

    public function normalizeForExportDispatch (Pack $pack, string $format = null, array $context = []): array {
        $lastTracking = $pack->getLastTracking();
        return [
            "code" => $pack->getCode(),
            "quantity" => $pack->getQuantity(),
            "nature" => $this->formatService->nature($pack->getNature(), null),
            "group" => $this->formatService->pack($pack->getParent(), null),
            SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_WEIGHT => $pack->getWeight(),
            SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_VOLUME => $pack->getVolume(),
            SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_COMMENT => $pack->getComment(),
            SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LAST_TRACKING_DATE => $lastTracking?->getDatetime()?->format(DateTimeInterface::ATOM),
            SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_LAST_LOCATION => $this->formatService->location($lastTracking->getEmplacement(), null),
            SubLineFixedField::FIELD_CODE_DISPATCH_LOGISTIC_UNIT_OPERATOR => $this->formatService->user($lastTracking->getOperateur(), null),
        ];
    }
}
