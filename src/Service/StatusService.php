<?php

namespace App\Service;

use App\Controller\Settings\StatusController;
use App\Entity\Statut;
use JetBrains\PhpStorm\ArrayShape;
use WiiCommon\Helper\Stream;

class StatusService {

    #[ArrayShape([
        'success' => "bool",
        'message' => "null|string"
    ])]
    public function validateStatusesData(array $persistedStatuses = []): array {
        $defaults = $this->countDuplicateStatuses($persistedStatuses, fn(Statut $status) => $status->isDefaultForCategory());
        $drafts = $this->countDuplicateStatuses($persistedStatuses, fn(Statut $status) => $status->isDraft());
        $disputes = $this->countDuplicateStatuses($persistedStatuses, fn(Statut $status) => $status->isDispute());
        $duplicateLabels = $this->countDuplicateStatusLabels($persistedStatuses);

        if($duplicateLabels > 0) {
            $message = "Il n'est pas possible d'avoir deux statuts identiques pour le même type";
        }
        else if ($defaults > 0) {
            $message = "Il n'est pas possible d'avoir deux statuts par défaut pour le même type";
        }
        else if ($drafts > 0) {
            $message = "Il n'est pas possible d'avoir deux statuts en état Brouillon pour le même type";
        }
        else if ($disputes > 0) {
            $message = "Il n'est pas possible d'avoir deux statuts en état Litige pour le même type";
        }

        return [
            'success' => empty($message),
            'message' => $message ?? null,
        ];
    }

    private function countDuplicateStatuses(array $statuses, callable $condition): int {
        $result = Stream::from($statuses)
            ->filter(fn(Statut $status) => $condition($status))
            ->reduce(function(array $carry, Statut $status): array {
                $categoryId = $status->getCategorie()?->getId() ?: 0;
                $typeId = $status->getType()?->getId() ?: 0;

                if (!isset($carry[$categoryId])) {
                    $carry[$categoryId] = [];
                }

                if (!isset($carry[$categoryId][$typeId])) {
                    $carry[$categoryId][$typeId] = -1;
                }

                $carry[$categoryId][$typeId]++;

                return $carry;
            }, []);

        return Stream::from($result)
            ->map(fn (array $typeResults) => Stream::from($typeResults)->sum())
            ->sum();
    }

    public function countDuplicateStatusLabels(array $statuses): int {
        $result = Stream::from($statuses)
            ->reduce(function(array $carry, Statut $status): array {
                $translations = $status->getLabelTranslation()?->getTranslations() ?: [];

                $categoryId = $status->getCategorie()?->getId() ?: 0;
                $typeId = $status->getType()?->getId() ?: 0;
                if (!isset($carry[$categoryId])) {
                    $carry[$categoryId] = [];
                }
                if (!isset($carry[$categoryId][$typeId])) {
                    $carry[$categoryId][$typeId] = [];
                }

                foreach ($translations as $translation) {
                    $slug = $translation->getLanguage()?->getSlug() ?: 0;
                    if (!isset($carry[$categoryId][$typeId][$slug])) {
                        $carry[$categoryId][$typeId][$slug] = [];
                    }

                    $label = $translation->getTranslation();
                    if ($label) {
                        if (!isset($carry[$categoryId][$typeId][$slug][$label])) {
                            $carry[$categoryId][$typeId][$slug][$label] = -1;
                        }
                        $carry[$categoryId][$typeId][$slug][$label]++;
                    }
                }

                return $carry;
            }, []);

        return Stream::from($result)
            ->map(function (array $typeResults) {
                return Stream::from($typeResults)
                    ->map(function (array $languageResults) {
                        return Stream::from($languageResults)
                            ->map(fn(array $labelCount) => Stream::from($labelCount)->sum())
                            ->sum();
                    })
                    ->sum();
            })
            ->sum();
    }

    #[ArrayShape([
        'label' => 'string',
        'id' => 'number',
        'code' => 'string',
        'modes' => 'string[]',
        'needMobileSyncDisabled' => 'boolean',
        'automaticReceptionCreationDisabled' => 'boolean',
        'passStatusAtPurchaseOrderGenerationDisabled' => 'boolean'
    ])]
    public function getStatusStatesValues(?string $mode = null): array {
        return Stream::from([
            [
                'label' => 'Brouillon',
                'id' => Statut::DRAFT,
                'code' => 'draft',
                'modes' => [StatusController::MODE_PURCHASE_REQUEST, StatusController::MODE_DISPATCH],
                'needMobileSyncDisabled' => true,
                'automaticReceptionCreationDisabled' => true,
                'passStatusAtPurchaseOrderGenerationDisabled' => true,
            ],
            [
                'label' => 'À traiter',
                'id' => Statut::NOT_TREATED,
                'code' => 'notTreated',
                'automaticReceptionCreationDisabled' => true,
                'passStatusAtPurchaseOrderGenerationDisabled' => true,
            ],
            [
                'label' => 'En cours',
                'id' => Statut::IN_PROGRESS,
                'code' => 'inProgress',
                'modes' => [StatusController::MODE_PURCHASE_REQUEST, StatusController::MODE_HANDLING, StatusController::MODE_PRODUCTION],
                'automaticReceptionCreationDisabled' => true,
            ],
            [
                'label' => 'Traité',
                'id' => Statut::TREATED,
                'code' => 'treated',
                'needMobileSyncDisabled' => true,
            ],
            [
                'label' => 'Litige',
                'id' => Statut::DISPUTE,
                'code' => 'dispute',
                'modes' => [StatusController::MODE_ARRIVAL],
            ],
            [
                'label' => 'Partiel',
                'id' => Statut::PARTIAL,
                'code' => 'partial',
                'modes' => [StatusController::MODE_DISPATCH, StatusController::MODE_PRODUCTION],
            ],
        ])
            ->filter(fn($state) => (
                !isset($state['modes'])
                || !$mode
                || in_array($mode, $state['modes'])
            ))
            ->toArray();
    }

    public function getStatusStateLabel(int $stateId): ?string {
        $states = $this->getStatusStatesValues();
        $label = null;
        foreach($states as $state) {
            if($state['id'] === $stateId) {
                $label = $state['label'];
                break;
            }
        }
        return $label;
    }

    public function getStatusStateCode(int $stateId): ?string {
        $states = $this->getStatusStatesValues();
        $label = null;
        foreach($states as $state) {
            if($state['id'] === $stateId) {
                $label = $state['code'];
                break;
            }
        }
        return $label;
    }

    public function getStatusStatesOptions(string $mode, ?int $selectedId = null, bool $prependEmpty = true): string {
        $statesStream = Stream::from($this->getStatusStatesValues($mode))
            ->map(function(array $state) use ($selectedId) {
                $selected = isset($selectedId) && $state['id'] == $selectedId ? 'selected' : '';
                $needMobileSyncDisabled = !empty($state['needMobileSyncDisabled'])
                    ? 'data-need-mobile-sync-disabled=true'
                    : '';
                $automaticReceptionCreationDisabled = !empty($state['automaticReceptionCreationDisabled'])
                    ? 'data-automatic-reception-creation-disabled=true'
                    : '';
                $passStatusAtPurchaseOrderGenerationDisabled = !empty($state['passStatusAtPurchaseOrderGenerationDisabled'])
                    ? 'data-pass-status-at-purchase-order-generation-disabled=true'
                    : '';
                return "<option value='{$state['id']}' {$selected} {$needMobileSyncDisabled} {$automaticReceptionCreationDisabled} {$passStatusAtPurchaseOrderGenerationDisabled}>{$state['label']}</option>";
            });

        if($prependEmpty) {
            $statesStream->prepend("<option/>");
        }

        return $statesStream->join('');
    }

}
