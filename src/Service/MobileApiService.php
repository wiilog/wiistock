<?php

namespace App\Service;

use App\Entity\Dispatch;
use App\Entity\DispatchPack;
use App\Entity\Nature;
use App\Entity\Setting;
use App\Entity\Translation;
use App\Entity\Utilisateur;
use App\Repository\SettingRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use WiiCommon\Helper\Stream;

class MobileApiService {

    /** @Required */
    public NatureService $natureService;

    public function getDispatchesData(EntityManagerInterface $entityManager,
                                      Utilisateur $loggedUser): array {
        $settingRepository = $entityManager->getRepository(Setting::class);
        $dispatchRepository = $entityManager->getRepository(Dispatch::class);
        $dispatchPackRepository = $entityManager->getRepository(DispatchPack::class);

        $dispatchExpectedDateColors = [
            'after' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_EXPECTED_DATE_COLOR_AFTER),
            'DDay' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_EXPECTED_DATE_COLOR_D_DAY),
            'before' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_EXPECTED_DATE_COLOR_BEFORE)
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

        //TODO: récupérer tout en français
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

    public function getMobileParameters(SettingRepository $globalsParameters): array {
        return Stream::from([
            "skipValidationsManualTransfer" => $globalsParameters->getOneParamByLabel(Setting::MANUAL_TRANSFER_TO_TREAT_SKIP_VALIDATIONS),
            "skipValidationsLivraisons" => $globalsParameters->getOneParamByLabel(Setting::LIVRAISON_SKIP_VALIDATIONS),
            "skipQuantitiesLivraisons" => $globalsParameters->getOneParamByLabel(Setting::LIVRAISON_SKIP_QUANTITIES),
            "skipValidationsToTreatTransfer" => $globalsParameters->getOneParamByLabel(Setting::TRANSFER_TO_TREAT_SKIP_VALIDATIONS),
            "displayReferencesOnTransferCards" => $globalsParameters->getOneParamByLabel(Setting::TRANSFER_DISPLAY_REFERENCES_ON_CARDS),
            "dropOnFreeLocation" => $globalsParameters->getOneParamByLabel(Setting::TRANSFER_FREE_DROP),
            "displayTargetLocationPicking" => $globalsParameters->getOneParamByLabel(Setting::DISPLAY_PICKING_LOCATION),
            "skipValidationsPreparations" => $globalsParameters->getOneParamByLabel(Setting::PREPARATION_SKIP_VALIDATIONS),
            "skipQuantitiesPreparations" => $globalsParameters->getOneParamByLabel(Setting::PREPARATION_SKIP_QUANTITIES),
            "preparationDisplayArticleWithoutManual" => $globalsParameters->getOneParamByLabel(Setting::PREPARATION_DISPLAY_ARTICLES_WITHOUT_MANUAL),
        ])
            ->keymap(fn($value, string $key) => [$key, $value == 1])
            ->toArray();
    }
}
