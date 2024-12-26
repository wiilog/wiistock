<?php

namespace App\Serializer;

use App\Entity\Dispute;
use App\Entity\DisputeHistoryRecord;
use App\Service\FormatService;
use App\Service\PackService;
use Exception;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use WiiCommon\Helper\Stream;
use function PHPUnit\Framework\isEmpty;

class DisputeNormalizer implements NormalizerInterface, NormalizerAwareInterface{

    use NormalizerAwareTrait;

    private const SUPPORTED_USAGES = [
        SerializerUsageEnum::MOBILE,
    ];

    public function __construct(
        private FormatService $formatService,
        private PackService   $packService,
    ) {}

    public function normalize(mixed $object, string $format = null, array $context = []): array {
        /** @var Dispute $dispute */
        $dispute = $object;

        /** @var SerializerUsageEnum $usage */
        $usage = $context["usage"] ?? null;

        $usageStr = $usage ? $usage->value : "null";
        $supportedUsageStr = Stream::from(self::SUPPORTED_USAGES)
            ->map(static fn(SerializerUsageEnum $supported) => $supported->value)
            ->join(", ");

        return match ($usage) {
            SerializerUsageEnum::CSV_EXPORT => $this->normalizeForCSVExport($dispute, $format, $context),
            default => throw new Exception("Invalid usage {$usageStr}, should be one of {$supportedUsageStr}"),
        };
    }

    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool {
        return $data instanceof Dispute;
    }

    public function getSupportedTypes(?string $format): array {
        return [
            Dispute::class => true,
        ];
    }

    public function normalizeForCSVExport (Dispute $dispute, string $format = null, array $context = []): array {
        $common = [
            "number" => $dispute->getNumber(),
            "type" => $this->formatService->type($dispute->getType()),
            "status" => $this->formatService->status($dispute->getStatus()),
            "creationDate" => $this->formatService->datetime($dispute->getCreationDate()),
            "updateDate" => $this->formatService->datetime($dispute->getUpdateDate()),
            "reporter" => $this->formatService->user($dispute->getReporter()),
            "historyRecord" => Stream::from($dispute->getDisputeHistory())
                ->map(function (DisputeHistoryRecord $record) {
                    $formatedUser = $this->formatService->user($record->getUser());
                    $formatedDate = $this->formatService->datetime($record->getDate());
                    $formatedComment = $this->formatService->html($record->getComment());

                    return "$formatedUser $formatedDate : $formatedComment";
                })
                ->join("\n"),
        ];

        $res = [];

        $packsContext = $context["packs"] ?? [];

        // arrival logistic unit dispute
        if (!$dispute->getPacks()->isEmpty()) {
            foreach ($dispute->getPacks() as $pack) {
                if (isEmpty($packsContext) || in_array($pack, $packsContext)) {
                    $arrival = $pack->getArrivage();
                    $res[] = [
                        ...$common,
                        "object" => $pack->getCode(),
                        "barcode" => null,
                        "quantity" => null,
                        "order" => $arrival?->getNumeroArrivage(),
                        "orderNumbers" => Stream::from($arrival?->getNumeroCommandeList() ?? [])->join(' / '),
                        "supplier" => $this->formatService->supplier($arrival?->getFournisseur()),
                        "lineNumber" => null,
                        "buyers" => $this->formatService->users($arrival?->getAcheteurs()),
                    ];
                }
            }
        }
        // reception order dispute
        else if(!$dispute->getArticles()->isEmpty()) {
            foreach ($dispute->getArticles() as $article) {
                $receptionReferenceArticle = $article->getReceptionReferenceArticle();
                $reception = $receptionReferenceArticle?->getReceptionLine()?->getReception();
                $res[] = [
                    ...$common,
                    "object" => $receptionReferenceArticle?->getReferenceArticle()?->getReference(),
                    "barcode" => $article->getBarCode(),
                    "quantity" => $article->getQuantite(),
                    "order" => $reception?->getNumber(),
                    "orderNumbers" => Stream::from($reception?->getOrderNumber() ?? [])->join(' / '),
                    "supplier" => $this->formatService->supplier($reception?->getFournisseur()),
                    "lineNumber" => $receptionReferenceArticle?->getCommande(),
                    "buyers" => $this->formatService->users($dispute->getBuyers()),
                ];
            }
        }

        return $res;
    }
}
