<?php

namespace App\Service;

use App\Entity\Action;
use App\Entity\CategoryType;
use App\Entity\Dispatch;
use App\Entity\DispatchPack;
use App\Entity\FieldsParam;
use App\Entity\FreeField;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\ParametrageGlobal;
use App\Entity\Translation;
use App\Entity\Utilisateur;
use App\Repository\ParametrageGlobalRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use WiiCommon\Helper\Stream;

class MobileApiService {

    /** @Required */
    public NatureService $natureService;

    public function getDispatchesData(EntityManagerInterface $entityManager,
                                      Utilisateur $loggedUser): array {
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
        $dispatchRepository = $entityManager->getRepository(Dispatch::class);
        $dispatchPackRepository = $entityManager->getRepository(DispatchPack::class);

        $dispatchExpectedDateColors = [
            'after' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_EXPECTED_DATE_COLOR_AFTER),
            'DDay' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_EXPECTED_DATE_COLOR_D_DAY),
            'before' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_EXPECTED_DATE_COLOR_BEFORE)
        ];

        $dispatches = $dispatchRepository->getMobileDispatches($loggedUser);
        $dispatches = Stream::from($dispatches)
            ->map(function (array $dispatch) use ($dispatchExpectedDateColors) {
                $dispatch['color'] = $this->expectedDateColor($dispatch['endDate'] ?? null, $dispatchExpectedDateColors);
                $dispatch['startDate'] = $dispatch['startDate'] ? $dispatch['startDate']->format('d/m/Y') : null;
                $dispatch['endDate'] = $dispatch['endDate'] ? $dispatch['endDate']->format('d/m/Y') : null;
                return $dispatch;
            })
            ->toArray();
        $dispatchPacks = array_map(function($dispatchPack) {
            if(!empty($dispatchPack['comment'])) {
                $dispatchPack['comment'] = substr(strip_tags($dispatchPack['comment']), 0, 200);
            }
            return $dispatchPack;
        }, $dispatchPackRepository->getMobilePacksFromDispatches(array_map(fn($dispatch) => $dispatch['id'], $dispatches)));

        return [
            'dispatches' => $dispatches,
            'dispatchPacks' => $dispatchPacks
        ];
    }

    public function getNaturesData(EntityManagerInterface $entityManager): array {
        $natureRepository = $entityManager->getRepository(Nature::class);
        return [
            'natures' => Stream::from($natureRepository->findAll())
                ->map(fn (Nature $nature) => $this->natureService->serializeNature($nature))
                ->toArray()
        ];
    }

    public function getTranslationsData(EntityManagerInterface $entityManager): array {
        $translationsRepository = $entityManager->getRepository(Translation::class);
        return [
            'translations' => $translationsRepository->findAllObjects(),
        ];
    }

    public function expectedDateColor(?DateTime $date, array $colors): ?string {
        $nowStr = (new DateTime('now'))->format('Y-m-d');
        $dateStr = !empty($date) ? $date->format('Y-m-d') : null;
        $color = null;
        if ($dateStr) {
            if ($dateStr > $nowStr && isset($colors['after'])) {
                $color = $colors['after'];
            }
            if ($dateStr === $nowStr && isset($colors['DDay'])) {
                $color = $colors['DDay'];
            }
            if ($dateStr < $nowStr && isset($colors['before'])) {
                $color = $colors['before'];
            }
        }
        return $color;
    }

    public function getMobileParameters(ParametrageGlobalRepository $globalsParameters) {
        return [
            "skipValidationsManualTransfer" => $globalsParameters->getOneParamByLabel(ParametrageGlobal::MANUAL_TRANSFER_TO_TREAT_SKIP_VALIDATIONS),
            "skipValidationsToTreatTransfer" => $globalsParameters->getOneParamByLabel(ParametrageGlobal::TRANSFER_TO_TREAT_SKIP_VALIDATIONS),
            "displayReferencesOnTransferCards" => $globalsParameters->getOneParamByLabel(ParametrageGlobal::TRANSFER_DISPLAY_REFERENCES_ON_CARDS),
            "dropOnFreeLocation" => $globalsParameters->getOneParamByLabel(ParametrageGlobal::TRANSFER_FREE_DROP),
        ];
    }
}
