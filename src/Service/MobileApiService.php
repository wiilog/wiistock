<?php

namespace App\Service;

use App\Entity\Action;
use App\Entity\Dispatch;
use App\Entity\DispatchPack;
use App\Entity\Language;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\Setting;
use App\Entity\Translation;
use App\Entity\Utilisateur;
use App\Repository\SettingRepository;
use Composer\Semver\Semver;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;
use WiiCommon\Helper\StringHelper;

class MobileApiService {

    #[Required]
    public NatureService $natureService;

    const MOBILE_TRANSLATIONS = [
        "Acheminements",
        "Objet",
        "Nombre d'opération(s) réalisée(s)",
        "Nature",
        "Emplacement de prise",
        "Emplacement de dépose",
    ];

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
        $dispatches = Stream::from(
            Stream::from($dispatches)
                ->reduce(function (array $accumulator, array $dispatch) {
                    return $this->serializeDispatch($accumulator, $dispatch);
                }, []))
            ->map(function (array $dispatch) use ($dispatchExpectedDateColors) {
                $dispatch['color'] = $this->expectedDateColor($dispatch['endDate'] ?? null, $dispatchExpectedDateColors);
                $dispatch['startDate'] = $dispatch['startDate'] ? $dispatch['startDate']->format('d/m/Y') : null;
                $dispatch['endDate'] = $dispatch['endDate'] ? $dispatch['endDate']->format('d/m/Y') : null;
                $dispatch['comment'] = utf8_encode(StringHelper::cleanedComment($dispatch['comment']));
                return $dispatch;
            })
            ->values();

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

    public function serializeDispatch(array $accumulator, array $dispatch) {
        $first = false;
        // Set dispatch
        if (!isset($accumulator[$dispatch['id']])) {
            $accumulator[$dispatch['id']] = $dispatch;
            $first = true;
        }

        // Set reference fields, format : REF1,REF2,...
        if (!$first && $accumulator[$dispatch['id']]['packReferences'] && $dispatch['packReferences']) {
            $accumulator[$dispatch['id']]['packReferences'] .= (',' . $dispatch['packReferences']);
        } else if ($dispatch['packReferences']) {
            $accumulator[$dispatch['id']]['packReferences'] = $dispatch['packReferences'];
        }

        // Set reference quantity fields, format : REF1 (1),REF2 (4),...
        if (!isset($accumulator[$dispatch['id']]['quantities']) && $dispatch['lineQuantity'] && $dispatch['packReferences']) {
            $accumulator[$dispatch['id']]['quantities'] = $dispatch['packReferences'] . ' (' . $dispatch['lineQuantity'] . ')';
        } else if ($dispatch['lineQuantity'] && $dispatch['packReferences']) {
            $accumulator[$dispatch['id']]['quantities'] .= (',' . $dispatch['packReferences'] . ' (' . $dispatch['lineQuantity'] . ')');
        }

        if (array_key_exists('lineQuantity', $accumulator[$dispatch['id']])) {
            unset($accumulator[$dispatch['id']]['lineQuantity']);
        }

        // Set packs fields, format : PACK1,PACK2,...
        if (!$first && $accumulator[$dispatch['id']]['packs'] && $dispatch['packs']) {
            $accumulator[$dispatch['id']]['packs'] .= (',' . $dispatch['packs']);
        } else if ($dispatch['packs']) {
            $accumulator[$dispatch['id']]['packs'] = $dispatch['packs'];
        }
        return $accumulator;
    }

    public function getNaturesData(EntityManagerInterface $entityManager, Utilisateur $user): array {
        $natureRepository = $entityManager->getRepository(Nature::class);
        return [
            'natures' => Stream::from($natureRepository->findAll())
                ->map(fn (Nature $nature) => $this->natureService->serializeNature($nature, $user))
                ->toArray()
        ];
    }

    public function getTranslationsData(EntityManagerInterface $entityManager, Utilisateur $user): array {
        $translationsRepository = $entityManager->getRepository(Translation::class);

        $userLanguage = $user->getLanguage();
        $translations = Stream::from($translationsRepository->findForMobile())
            ->map(fn(Translation $translation) => [
                'topMenu' => $translation->getSource()->getCategory()?->getParent()?->getParent()?->getLabel(),
                'menu' => $translation->getSource()->getCategory()?->getParent()?->getLabel(),
                'subMenu' => $translation->getSource()->getCategory()?->getLabel(),
                'translation' => $translation->getSource()->getTranslationIn($userLanguage, Language::FRENCH_DEFAULT_SLUG)?->getTranslation(),
                'label' => $translation->getSource()->getTranslationIn(Language::DEFAULT_LANGUAGE_SLUG)?->getTranslation()
            ])
            ->toArray();

        //TODO: récupérer tout en français
        return [
            'translations' => $translations,
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
            "skipValidationsManualTransfer" => $globalsParameters->getOneParamByLabel(Setting::MANUAL_TRANSFER_TO_TREAT_SKIP_VALIDATIONS) == 1,
            "skipValidationsLivraisons" => $globalsParameters->getOneParamByLabel(Setting::LIVRAISON_SKIP_VALIDATIONS) == 1,
            "skipQuantitiesLivraisons" => $globalsParameters->getOneParamByLabel(Setting::LIVRAISON_SKIP_QUANTITIES) == 1,
            "skipValidationsToTreatTransfer" => $globalsParameters->getOneParamByLabel(Setting::TRANSFER_TO_TREAT_SKIP_VALIDATIONS) == 1,
            "displayReferencesOnTransferCards" => $globalsParameters->getOneParamByLabel(Setting::TRANSFER_DISPLAY_REFERENCES_ON_CARDS) == 1,
            "dropOnFreeLocation" => $globalsParameters->getOneParamByLabel(Setting::TRANSFER_FREE_DROP) == 1,
            "displayTargetLocationPicking" => $globalsParameters->getOneParamByLabel(Setting::DISPLAY_PICKING_LOCATION) == 1,
            "skipValidationsPreparations" => $globalsParameters->getOneParamByLabel(Setting::PREPARATION_SKIP_VALIDATIONS) == 1,
            "skipQuantitiesPreparations" => $globalsParameters->getOneParamByLabel(Setting::PREPARATION_SKIP_QUANTITIES) == 1,
            "preparationDisplayArticleWithoutManual" => $globalsParameters->getOneParamByLabel(Setting::PREPARATION_DISPLAY_ARTICLES_WITHOUT_MANUAL) == 1,
            "manualDeliveryDisableValidations" => $globalsParameters->getOneParamByLabel(Setting::MANUAL_DELIVERY_DISABLE_VALIDATIONS) == 1,
            "rfidPrefix" => $globalsParameters->getOneParamByLabel(Setting::RFID_PREFIX) ?: null,
            "forceDispatchSignature" => $globalsParameters->getOneParamByLabel(Setting::FORCE_GROUPED_SIGNATURE),
            "deliveryRequestDropOnFreeLocation" => $globalsParameters->getOneParamByLabel(Setting::ALLOWED_DROP_ON_FREE_LOCATION) == 1,
            "displayReferenceCodeAndScan" => $globalsParameters->getOneParamByLabel(Setting::DISPLAY_REFERENCE_CODE_AND_SCANNABLE) == 1,
        ])
            ->toArray();
    }

    public function checkMobileVersion(string $mobileVersion, string $requiredVersion): bool {
        // @ silences the error when the array contains 0 or one value
        @[$mobileVersion, $mobilePatch] = explode("#", $mobileVersion, 2);
        @[$requiredVersion, $requiredPatch] = explode("#", $requiredVersion, 2);

        if($mobilePatch !== $requiredPatch) {
            return false;
        }

       return Semver::satisfies($mobileVersion, $requiredVersion);
    }

}
