<?php

namespace App\Serializer;

use App\Entity\Tracking\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Helper\FormatHelper;
use App\Service\FormatService;
use App\Service\PackService;
use App\Service\TrackingMovementService;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use WiiCommon\Helper\Stream;

class PackNormalizer implements NormalizerInterface, NormalizerAwareInterface{

    use NormalizerAwareTrait;

    private const SUPPORTED_USAGES = [
        SerializerUsageEnum::MOBILE,
        SerializerUsageEnum::CSV_EXPORT,
    ];

    public function __construct(
        private FormatService           $formatService,
        private PackService             $packService,
        private EntityManagerInterface  $entityManager,
        private TrackingMovementService $trackingMovementService
    ) {}

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
            SerializerUsageEnum::CSV_EXPORT => $this->normalizeForCVSExport($pack, $format, $context),
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

        $res = [
            "id" => $pack->getId(),
            "code" => $pack->getCode(),
            "quantity" => $pack->getQuantity(),
            "nature" => $this->formatService->nature($pack->getNature(), null),
            "natureId" => $pack->getNature()?->getId(),
            "location" => $this->formatService->location($lastAction?->getEmplacement()),
            "date" => $this->formatService->datetime($lastAction?->getDatetime()),
        ];

        if ($pack->isGroup()) {
            $res["isGroup"] = true;

            $res["packs"] = $pack->getContent()
                ->map(function(Pack $pack) {
                    $trackingDelayData = $this->packService->formatTrackingDelayData($pack);
                    return [
                        "code" => $pack->getCode(),
                        "ref_article" => $pack->getCode(),
                        "nature_id" => $pack->getNature()?->getId(),
                        "quantity" => $pack->getLastAction()
                            ? $pack->getLastAction()->getQuantity()
                            : 1,
                        "type" => $pack->getLastAction()?->getType()?->getCode(),
                        "ref_emplacement" => $pack->getLastAction()?->getEmplacement()?->getLabel(),
                        "date" => $this->formatService->datetime($pack->getLastAction()?->getDatetime(), null),
                        "trackingDelayColor" => $trackingDelayData["color"] ?? null,
                        "trackingDelay" => $trackingDelayData["delayHTML"] ?? null,
                        "limitTreatmentDate" => $trackingDelayData["dateHTML"] ?? null,
                    ];
                })
                ->toArray();
        }
        else {
            $trackingDelayData = $this->packService->formatTrackingDelayData($pack);
            $res["trackingDelay"] = $trackingDelayData["delayHTML"] ?? null;
            $res["trackingDelayColor"] = $trackingDelayData["color"] ?? null;
            $res["limitTreatmentDate"] = $trackingDelayData["dateHTML"] ?? null;
        }

        return $res;
    }

    public function normalizeForCVSExport (Pack $pack, string $format = null, array $context = []): array {
        $trackingMovementRepository = $this->entityManager->getRepository(TrackingMovement::class);

        $firstMovements = $trackingMovementRepository->findBy(
            ["pack" => $pack],
            ["datetime" => Order::Ascending->value, "orderIndex" => Order::Ascending->value, "id" => Order::Ascending->value],
            1
        );
        $fromColumnData = $this->trackingMovementService->getFromColumnData($firstMovements[0] ?? null);

        return [
            "code" => $pack->getCode(),
            "nature" => $this->formatService->nature($pack->getNature(), null),
            "lastMvtDate" => $pack->getLastAction()?->getDatetime(),
            "fromLabel" => $fromColumnData["fromLabel"] ?? null,
            "from" =>  $fromColumnData["from"] ?? null,
            "location" => $this->formatService->location($pack->getLastAction()?->getEmplacement()),
            "groupCode" => $this->formatService->pack($pack->getGroup()),
            "groupIteration" => $pack->getGroupIteration(),
            "weight" => $pack->getWeight(),
            "volume" => $pack->getVolume(),
        ];
    }
}
