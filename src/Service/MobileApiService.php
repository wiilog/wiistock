<?php

namespace App\Service;

use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\Article;
use App\Entity\CategorieStatut;
use App\Entity\Dispatch;
use App\Entity\DispatchPack;
use App\Entity\DispatchReferenceArticle;
use App\Entity\Language;
use App\Entity\Menu;
use App\Entity\MouvementStock;
use App\Entity\Nature;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\Translation;
use App\Entity\Utilisateur;
use Composer\Semver\Semver;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use WiiCommon\Helper\Stream;

class MobileApiService {

    public function __construct(
        private SettingsService        $settingsService,
        private NatureService          $natureService,
        private UserService            $userService,
        private MouvementStockService  $stockMovementService,
        private AlertService           $alertService,
        private RefArticleDataService  $refArticleDataService,
    ) {
    }

    const MOBILE_TRANSLATIONS = [
        "Acheminements",
        "Champs fixes",
        "Général",
        "Objet",
        "Nombre d'opération(s) réalisée(s)",
        "Nature",
        "Emplacement de prise",
        "Emplacement de dépose",
        "Livraison",
        "Projet",
        "Divers",
    ];

    public function getDispatchesData(EntityManagerInterface $entityManager,
                                      Utilisateur $loggedUser): array {
        $dispatchRepository = $entityManager->getRepository(Dispatch::class);
        $dispatchPackRepository = $entityManager->getRepository(DispatchPack::class);
        $dispatchReferenceArticleRepository = $entityManager->getRepository(DispatchReferenceArticle::class);

        $dispatchExpectedDateColors = [
            'after' => $this->settingsService->getValue($entityManager, Setting::DISPATCH_EXPECTED_DATE_COLOR_AFTER),
            'DDay' => $this->settingsService->getValue($entityManager, Setting::DISPATCH_EXPECTED_DATE_COLOR_D_DAY),
            'before' => $this->settingsService->getValue($entityManager, Setting::DISPATCH_EXPECTED_DATE_COLOR_BEFORE)
        ];

        $dispatchOfflineMode = $this->userService->hasRightFunction(Menu::NOMADE, Action::DISPATCH_REQUEST_OFFLINE_MODE, $loggedUser);
        $dispatches = $dispatchRepository->getMobileDispatches($loggedUser, null, $dispatchOfflineMode);
        $dispatches = Stream::from(
            Stream::from($dispatches)
                ->reduce(function (array $accumulator, array $dispatch) {
                    return $this->serializeDispatch($accumulator, $dispatch);
                }, []))
            ->map(function (array $dispatch) use ($dispatchExpectedDateColors) {
                $dispatch['color'] = $this->expectedDateColor($dispatch['endDate'] ?? null, $dispatchExpectedDateColors);
                $dispatch['startDate'] = $dispatch['startDate'] ? $dispatch['startDate']->format('d/m/Y') : null;
                $dispatch['endDate'] = $dispatch['endDate'] ? $dispatch['endDate']->format('d/m/Y') : null;
                $dispatch['comment'] = strip_tags($dispatch['comment']);
                return $dispatch;
            })
            ->values();

        $dispatchIds = Stream::from($dispatches)
            ->map(fn(array $dispatch) => $dispatch['id'])
            ->toArray();

        $dispatchPacks = Stream::from($dispatchPackRepository->getMobilePacksFromDispatches($dispatchIds))
            ->map(function($dispatchPack) {
                if(!empty($dispatchPack['comment'])) {
                    $dispatchPack['comment'] = substr(strip_tags($dispatchPack['comment']), 0, 200);
                }
                return $dispatchPack;
            })
            ->toArray();

        $dispatchReferences = Stream::from($dispatchReferenceArticleRepository->getForMobile($dispatchIds))
            ->map(function ($dispatchReference) {
                if (!empty($dispatchReference['comment'])) {
                    $dispatchReference['comment'] = substr(strip_tags($dispatchReference['comment']), 0, 200);
                }
                $dispatchReference['associatedDocumentTypes'] = Stream::from($dispatchReference['associatedDocumentTypes'] ?: [])
                    ->join(',') ?: null;

                return $dispatchReference;
            })
            ->toArray();

        return [
            'dispatches' => $dispatches,
            'dispatchPacks' => $dispatchPacks,
            'dispatchReferences' => $dispatchReferences,
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
        }
        else if ($dispatch['lineQuantity'] && $dispatch['packReferences']) {
            $accumulator[$dispatch['id']]['quantities'] .= (',' . $dispatch['packReferences'] . ' (' . $dispatch['lineQuantity'] . ')');
        }
        else {
            $accumulator[$dispatch['id']]['quantities'] = null;
        }

        if (array_key_exists('lineQuantity', $accumulator[$dispatch['id']])) {
            unset($accumulator[$dispatch['id']]['lineQuantity']);
        }

        // Set packs fields, format : PACK1,PACK2,...
        if (!$first && $accumulator[$dispatch['id']]['packs'] && $dispatch['packs']) {
            $accumulator[$dispatch['id']]['packs'] .= (',' . $dispatch['packs']);
        }
        else if ($dispatch['packs']) {
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

    public function getMobileParameters(SettingsService $globalsParameters, EntityManagerInterface $entityManager): array {
        $arrivalNumberFormat = $globalsParameters->getValue($entityManager, Setting::ARRIVAL_NUMBER_FORMAT);
        return Stream::from([
            "skipValidationsManualTransfer" => $globalsParameters->getValue($entityManager, Setting::MANUAL_TRANSFER_TO_TREAT_SKIP_VALIDATIONS) == 1,
            "skipValidationsLivraisons" => $globalsParameters->getValue($entityManager, Setting::LIVRAISON_SKIP_VALIDATIONS) == 1,
            "skipQuantitiesLivraisons" => $globalsParameters->getValue($entityManager, Setting::LIVRAISON_SKIP_QUANTITIES) == 1,
            "skipValidationsToTreatTransfer" => $globalsParameters->getValue($entityManager, Setting::TRANSFER_TO_TREAT_SKIP_VALIDATIONS) == 1,
            "displayReferencesOnTransferCards" => $globalsParameters->getValue($entityManager, Setting::TRANSFER_DISPLAY_REFERENCES_ON_CARDS) == 1,
            "dropOnFreeLocation" => $globalsParameters->getValue($entityManager, Setting::TRANSFER_FREE_DROP) == 1,
            "displayTargetLocationPicking" => $globalsParameters->getValue($entityManager, Setting::DISPLAY_PICKING_LOCATION) == 1,
            "skipValidationsPreparations" => $globalsParameters->getValue($entityManager, Setting::PREPARATION_SKIP_VALIDATIONS) == 1,
            "skipQuantitiesPreparations" => $globalsParameters->getValue($entityManager, Setting::PREPARATION_SKIP_QUANTITIES) == 1,
            "preparationDisplayArticleWithoutManual" => $globalsParameters->getValue($entityManager, Setting::PREPARATION_DISPLAY_ARTICLES_WITHOUT_MANUAL) == 1,
            "manualDeliveryDisableValidations" => $globalsParameters->getValue($entityManager, Setting::MANUAL_DELIVERY_DISABLE_VALIDATIONS) == 1,
            "rfidPrefix" => $globalsParameters->getValue($entityManager, Setting::RFID_PREFIX) ?: null,
            "forceDispatchSignature" => $globalsParameters->getValue($entityManager, Setting::FORCE_GROUPED_SIGNATURE),
            "deliveryRequestDropOnFreeLocation" => $globalsParameters->getValue($entityManager, Setting::ALLOWED_DROP_ON_FREE_LOCATION) == 1,
            "displayReferenceCodeAndScan" => $globalsParameters->getValue($entityManager, Setting::DISPLAY_REFERENCE_CODE_AND_SCANNABLE) == 1,
            "articleLocationDropWithReferenceStorageRule" => $globalsParameters->getValue($entityManager, Setting::ARTICLE_LOCATION_DROP_WITH_REFERENCE_STORAGE_RULES) == 1,
            "displayWarningWrongLocation" => $globalsParameters->getValue($entityManager, Setting::DISPLAY_WARNING_WRONG_LOCATION) == 1,
            "displayManualDelayStart" => $globalsParameters->getValue($entityManager, Setting::DISPLAY_MANUAL_DELAY_START) == 1,
            "arrivalNumberFormat" => Arrivage::AVAILABLE_ARRIVAL_NUMBER_FORMATS[$arrivalNumberFormat]
                ?? Arrivage::AVAILABLE_ARRIVAL_NUMBER_FORMATS[UniqueNumberService::DATE_COUNTER_FORMAT_ARRIVAL_LONG],
            "rfidOnMobileTrackingMovements" => $globalsParameters->getValue($entityManager, Setting::RFID_ON_MOBILE_TRACKING_MOVEMENTS) == 1,
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

    /**
     * @param Article[] $articles
     * @param string[] $tags
     */
    public function treatInventoryArticles(EntityManagerInterface $entityManager,
                                           array                  $articles,
                                           array                  $tags,
                                           Utilisateur            $validator,
                                           DateTime               $date): void
    {

        $statusRepository = $entityManager->getRepository(Statut::class);
        $settingRepository = $entityManager->getRepository(Setting::class);

        $activeStatus = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_ACTIF);
        $inactiveStatus = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_INACTIF);

        $referenceArticlesToUpdate = [];

        foreach ($articles as $article) {
            if (in_array($article->getRFIDtag(), $tags)) {
                $presentArticle = $article;
                if ($presentArticle->getStatut()->getCode() !== Article::STATUT_ACTIF) {
                    $location = $presentArticle->getEmplacement();
                    $correctionMovement = $this->stockMovementService->createMouvementStock($validator, $location, $presentArticle->getQuantite(), $presentArticle, MouvementStock::TYPE_INVENTAIRE_ENTREE, [
                        'date' => $date,
                        'locationTo' => $location,
                    ]);

                    $entityManager->persist($correctionMovement);
                }
                $presentArticle
                    ->setFirstUnavailableDate(null)
                    ->setLastAvailableDate($date)
                    ->setStatut($activeStatus)
                    ->setDateLastInventory($date);
            }
            else {
                $missingArticle = $article;
                if ($missingArticle->getStatut()->getCode() !== Article::STATUT_INACTIF) {
                    $location = $missingArticle->getEmplacement();
                    $correctionMovement = $this->stockMovementService->createMouvementStock($validator, $location, $missingArticle->getQuantite(), $missingArticle, MouvementStock::TYPE_INVENTAIRE_SORTIE, [
                        'date' => $date,
                        'locationTo' => $location,
                    ]);

                    $entityManager->persist($correctionMovement);
                    $missingArticle
                        ->setFirstUnavailableDate($date);
                }
                $missingArticle
                    ->setStatut($inactiveStatus)
                    ->setDateLastInventory($date);
            }

            $reference = $article->getReferenceArticle();
            $referenceArticleId = $reference?->getId();
            if ($referenceArticleId
                && !isset($referenceArticlesToUpdate[$referenceArticleId])) {
                $referenceArticlesToUpdate[$referenceArticleId] = $reference;
            }
        }

        $entityManager->flush();

        $this->refArticleDataService->updateRefArticleQuantities($entityManager, $referenceArticlesToUpdate);

        $expiryDelay = $this->settingsService->getValue($entityManager, Setting::STOCK_EXPIRATION_DELAY) ?: 0;
        foreach ($referenceArticlesToUpdate as $reference) {
            $this->refArticleDataService->treatAlert($entityManager, $reference);
        }

        foreach ($articles as $article) {
            $this->alertService->treatArticleAlert($entityManager, $article, $expiryDelay);
        }

        $entityManager->flush();
    }
}
