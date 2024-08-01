<?php

namespace App\Service\ProductionRequest;

use App\Entity\FreeField;
use App\Entity\Language;
use App\Entity\ProductionRequest;

use App\Service\FormatService;
use WiiCommon\Helper\Stream;

class PlanningService {
    public function __construct(
        private readonly ProductionRequestService $productionRequestService,
        private readonly FormatService $formatService
    ) {}

    public function createCardConfig(array $displayedFieldsConfig, ProductionRequest $productionRequest, array $fieldModes,Language|string $userLanguage, Language|string|null $defaultLanguage): array {
        $cardContent = $displayedFieldsConfig;

        foreach ($freeFieldsByType[$productionRequest->getType()->getId()] ?? [] as $freeField) {
            $fieldName = "free_field_" . $freeField->getId();
            $fieldDisplayConfig = $this->productionRequestService->getFieldDisplayConfig($fieldName, $fieldModes);
            if ($fieldDisplayConfig) {
                $cardContent[$fieldDisplayConfig]["rows"][] = [
                    "field" => $freeField,
                    "getDetails" => static fn(ProductionRequest $productionRequest, FreeField $freeField) => [
                        "label" => $freeField->getLabelIn($userLanguage, $defaultLanguage),
                        "value" => $this->formatService->freeField($productionRequest->getFreeFieldValue($freeField->getId()), $freeField)
                    ]
                ];
            }
        }

        return Stream::from($cardContent)
            ->map(static function(array $location) use ($productionRequest) {
                return Stream::from($location)
                    ->map(static function(array $type) use ($productionRequest) {
                        return Stream::from($type)
                            ->map(static function(array $fieldConfig) use ($productionRequest) {
                                return $fieldConfig["getDetails"]($productionRequest, $fieldConfig["field"]);
                            })
                            ->toArray();
                    })
                    ->toArray();
            })
            ->toArray();
    }
}
