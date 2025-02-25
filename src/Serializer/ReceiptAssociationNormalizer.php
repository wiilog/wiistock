<?php

namespace App\Serializer;

use App\Entity\ReceiptAssociation;
use App\Service\FormatService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use WiiCommon\Helper\Stream;

class ReceiptAssociationNormalizer implements NormalizerInterface, NormalizerAwareInterface{

    use NormalizerAwareTrait;

    private const SUPPORTED_USAGES = [
        SerializerUsageEnum::CSV_EXPORT,
    ];

    public function __construct(
        private FormatService           $formatService,
        private EntityManagerInterface  $entityManager,
    ) {}

    public function normalize(mixed $object, string $format = null, array $context = []): array {
        /** @var ReceiptAssociation $receiptAssociation */
        $receiptAssociation = $object;

        /** @var SerializerUsageEnum $usage */
        $usage = $context["usage"] ?? null;

        $usageStr = $usage ? $usage->value : "null";
        $supportedUsageStr = Stream::from(self::SUPPORTED_USAGES)
            ->map(static fn(SerializerUsageEnum $supported) => $supported->value)
            ->join(", ");

        return match ($usage) {
            SerializerUsageEnum::CSV_EXPORT => $this->normalizeForCVSExport($receiptAssociation, $format, $context),
            default => throw new Exception("Invalid usage {$usageStr}, should be one of {$supportedUsageStr}"),
        };
    }

    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool {
        return $data instanceof ReceiptAssociation;
    }

    public function getSupportedTypes(?string $format): array {
        return [
            ReceiptAssociation::class => true,
        ];
    }

    public function normalizeForCVSExport (ReceiptAssociation $receiptAssociation, string $format = null, array $context = []): array {
        return [
            "creationDate" => $this->formatService->datetime($receiptAssociation->getCreationDate()),
            "receptionNumber" => $receiptAssociation->getReceptionNumber(),
            "user" => $this->formatService->user($receiptAssociation->getUser()),
        ];
    }
}
