<?php

namespace App\Serializer;

use App\Entity\Emergency\StockEmergency;
use App\Entity\ReceptionLine;
use App\Entity\ReceptionReferenceArticle;
use App\Service\FormatService;
use Exception;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use WiiCommon\Helper\Stream;

class ReceptionLineNormalizer implements NormalizerInterface, NormalizerAwareInterface{

    use NormalizerAwareTrait;

    private const SUPPORTED_USAGES = [
        SerializerUsageEnum::RECEPTION_SHOW,
    ];

    public function __construct(
        private FormatService $formatService,
    ) {
    }

    public function normalize(mixed $object, string $format = null, array $context = []): array {
        /** @var ReceptionLine $receptionLine */
        $receptionLine = $object;

        /** @var SerializerUsageEnum $usage */
        $usage = $context["usage"] ?? null;

        $usageStr = $usage ? $usage->value : "null";
        $supportedUsageStr = Stream::from(self::SUPPORTED_USAGES)
            ->map(static fn(SerializerUsageEnum $supported) => $supported->value)
            ->join(", ");

        return match ($usage) {
            SerializerUsageEnum::RECEPTION_SHOW, SerializerUsageEnum::RECEPTION_MOBILE => $this->doNormalize($receptionLine, $format, $context),
            default => throw new Exception("Invalid usage {$usageStr}, should be one of {$supportedUsageStr}"),
        };
    }

    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool {
        return $data instanceof ReceptionLine;
    }

    public function getSupportedTypes(?string $format): array {
        return [
            ReceptionLine::class => true,
        ];
    }

    public function doNormalize(ReceptionLine $receptionLine, string $format = null, array $context = []): array {
        $pack = $receptionLine->getPack();

        /** @var SerializerUsageEnum $usage */
        $usage = $context["usage"] ?? null;

        return [
            "id" => $receptionLine->getId(),
            "pack" => $pack
                ? [
                    "id" => $pack->getId(),
                    "code" => $pack->getCode(),
                    "location" => $this->formatService->location($pack->getLastOngoingDrop()?->getEmplacement(), null),
                    "project" => $this->formatService->project($pack->getProject(), null),
                    "nature" =>  $this->formatService->nature($pack->getNature(), null),
                    "color" => $pack->getNature()?->getColor(),
                ]
                : null,
            "references" => Stream::from($receptionLine->getReceptionReferenceArticles())
                ->map(function (ReceptionReferenceArticle $receptionReferenceArticle) use($usage) {
                    $referenceArticle = $receptionReferenceArticle->getReferenceArticle();
                    $receivedQuantity = $receptionReferenceArticle->getQuantite() ?: 0;
                    $quantityToReceive = $receptionReferenceArticle->getQuantiteAR() ?: 0;
                    return [
                        "id" => $receptionReferenceArticle->getId(),
                        "reference" => $referenceArticle->getReference(),
                        "orderNumber" => $receptionReferenceArticle->getCommande(),
                        "unitPrice" => $receptionReferenceArticle->getUnitPrice(),
                        "quantityType" => $referenceArticle->getTypeQuantite(),
                        "barCode" => $referenceArticle->getBarCode(),
                        "label" => $referenceArticle->getLibelle(),
                        "emergency" => !$receptionReferenceArticle->getStockEmergencies()->isEmpty(),
                        "emergencyComment" => Stream::from($receptionReferenceArticle->getStockEmergencies())
                            ->map(static fn(StockEmergency $stockEmergency) => strip_tags($stockEmergency->getComment()))
                            ->join(', '),
                        ...(match($usage) {
                            SerializerUsageEnum::RECEPTION_MOBILE => [
                                "quantityToReceive" => $quantityToReceive - $receivedQuantity,
                                "receivedQuantity" => 0,
                            ],
                            // SerializerUsageEnum::RECEPTION_SHOW
                            default => [
                                "quantityToReceive" => $quantityToReceive,
                                "receivedQuantity" => $receivedQuantity,
                            ],
                        })
                    ];
                })
                ->filter(static fn(array $line) => $usage !== SerializerUsageEnum::RECEPTION_MOBILE || $line["quantityToReceive"] > 0)
                ->toArray(),
        ];
    }

}
