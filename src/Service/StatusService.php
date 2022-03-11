<?php

namespace App\Service;

use App\Controller\Settings\StatusController;
use App\Entity\CategorieStatut;
use App\Entity\Statut;
use App\Entity\Type;
use Doctrine\ORM\EntityManagerInterface;
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
        $duplicateLabels = $this->countDuplicateLabelStatuses($persistedStatuses);

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
                $categoryLabel = $status->getCategorie()?->getNom() ?: 0;
                $typeLabel = $status->getType()?->getLabel() ?: 0;

                if (!isset($carry[$categoryLabel])) {
                    $carry[$categoryLabel] = [];
                }

                if (!isset($carry[$categoryLabel][$typeLabel])) {
                    $carry[$categoryLabel][$typeLabel] = -1;
                }

                $carry[$categoryLabel][$typeLabel]++;

                return $carry;
            }, []);

        return Stream::from($result)
            ->map(fn (array $typeResults) => Stream::from($typeResults)->sum())
            ->sum();
    }

    private function countDuplicateLabelStatuses(array $statuses): int {
        $result = Stream::from($statuses)
            ->reduce(function(array $carry, Statut $status): array {
                $categoryLabel = $status->getCategorie()?->getNom() ?: 0;
                $typeLabel = $status->getType()?->getLabel() ?: 0;
                $statusLabel = $status->getNom();

                if (!isset($carry[$categoryLabel])) {
                    $carry[$categoryLabel] = [];
                }
                if (!isset($carry[$categoryLabel][$typeLabel])) {
                    $carry[$categoryLabel][$typeLabel] = [];
                }
                if (!isset($carry[$categoryLabel][$typeLabel][$statusLabel])) {
                    $carry[$categoryLabel][$typeLabel][$statusLabel] = -1;
                }
                $carry[$categoryLabel][$typeLabel][$statusLabel]++;
                return $carry;
            }, []);

        return Stream::from($result)
            ->map(function (array $typeResults) {
                return Stream::from($typeResults)
                    ->map(fn(array $labelCount) => Stream::from($labelCount)->sum())
                    ->sum();
            })
            ->sum();
    }

    public function getStatusStatesValues(?string $mode = null): array {
        return Stream::from([
            [
                'label' => 'Brouillon',
                'id' => Statut::DRAFT,
                'code' => 'draft',
                'modes' => [StatusController::MODE_PURCHASE_REQUEST, StatusController::MODE_DISPATCH],
                'needMobileSyncDisabled' => true,
            ],
            [
                'label' => 'À traiter',
                'id' => Statut::NOT_TREATED,
                'code' => 'notTreated',
            ],
            [
                'label' => 'En cours',
                'id' => Statut::IN_PROGRESS,
                'code' => 'inProgress',
                'modes' => [StatusController::MODE_PURCHASE_REQUEST, StatusController::MODE_HANDLING],
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
                'modes' => [StatusController::MODE_DISPATCH],
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
                $needMobileSyncDisabled = !empty($state['needMobileSyncDisabled']) ? 'data-need-mobile-sync-disabled=true' : '';
                return "<option value='{$state['id']}' {$selected} {$needMobileSyncDisabled}>{$state['label']}</option>";
            });

        if($prependEmpty) {
            $statesStream->prepend("<option/>");
        }

        return $statesStream->join('');
    }

}
