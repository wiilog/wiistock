<?php
// TODO WIIS-6693
namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\FreeField;
use App\Entity\DaysWorked;
use App\Entity\MailerServer;
use App\Entity\Menu;
use App\Entity\PrefixeNomDemande;
use App\Entity\Statut;
use App\Entity\Translation;
use App\Entity\Type;
use App\Entity\WorkFreeDay;
use App\Entity\Setting;

use App\Service\AlertService;
use App\Service\AttachmentService;
use App\Service\CacheService;
use App\Service\GlobalParamService;
use App\Service\PackService;
use App\Service\SpecificService;
use App\Service\TranslationService;
use App\Service\UserService;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

/**
 * @Route("/parametrage-global")
 * @deprecated Use SettingsController instead
 */
class ParametrageGlobalController extends AbstractController
{

    private array $engDayToFr = [
        'monday' => 'Lundi',
        'tuesday' => 'Mardi',
        'wednesday' => 'Mercredi',
        'thursday' => 'Jeudi',
        'friday' => 'Vendredi',
        'saturday' => 'Samedi',
        'sunday' => 'Dimanche',
    ];

    /**
     * @Route("/", name="global_param_index")
     * @HasPermission({Menu::PARAM, Action::SETTINGS_GLOBAL})
     */
    public function index(GlobalParamService $globalParamService,
                          EntityManagerInterface $entityManager,
                          SpecificService $specificService): Response {

        $statusRepository = $entityManager->getRepository(Statut::class);
        $mailerServerRepository = $entityManager->getRepository(MailerServer::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $champsLibreRepository = $entityManager->getRepository(FreeField::class);
        $categoryCLRepository = $entityManager->getRepository(CategorieCL::class);
        $translationRepository = $entityManager->getRepository(Translation::class);
        $workFreeDaysRepository = $entityManager->getRepository(WorkFreeDay::class);

        $labelLogo = $settingRepository->getOneParamByLabel(Setting::LABEL_LOGO);
        $emergencyIcon = $settingRepository->getOneParamByLabel(Setting::EMERGENCY_ICON);
        $customIcon = $settingRepository->getOneParamByLabel(Setting::CUSTOM_ICON);
        $deliveryNoteLogo = $settingRepository->getOneParamByLabel(Setting::DELIVERY_NOTE_LOGO);
        $waybillLogo = $settingRepository->getOneParamByLabel(Setting::WAYBILL_LOGO);

        $websiteLogo = $settingRepository->getOneParamByLabel(Setting::WEBSITE_LOGO);
        $emailLogo = $settingRepository->getOneParamByLabel(Setting::EMAIL_LOGO);
        $mobileLogoHeader = $settingRepository->getOneParamByLabel(Setting::MOBILE_LOGO_HEADER);
        $mobileLogoLogin = $settingRepository->getOneParamByLabel(Setting::MOBILE_LOGO_LOGIN);

        $typeRepository = $entityManager->getRepository(Type::class);
        $deliveryTypeSettings = $globalParamService->getDefaultDeliveryLocationsByType($entityManager);

        $clsForLabels = $champsLibreRepository->findBy([
            'categorieCL' => $categoryCLRepository->findOneBy(['label' => CategorieCL::ARTICLE])
        ]);
        $workFreeDays = array_map(
            function(WorkFreeDay $workFreeDay) {
                return $workFreeDay->getDay()->format('Y-m-d');
            },
            $workFreeDaysRepository->findAll()
        );

        $arrivalEmergencyTriggeringFields = $settingRepository->getOneParamByLabel(Setting::ARRIVAL_EMERGENCY_TRIGGERING_FIELDS);
        $arrivalEmergencyTriggeringFieldsValue = $arrivalEmergencyTriggeringFields ? explode(',', $arrivalEmergencyTriggeringFields) : null;

        return $this->render('parametrage_global/index.html.twig',
            [
                'logo' => ($labelLogo && file_exists(getcwd() . "/uploads/attachements/" . $labelLogo) ? $labelLogo : null),
                'emergencyIcon' => ($emergencyIcon && file_exists(getcwd() . "/" . $emergencyIcon) ? $emergencyIcon : null),
                'customIcon' => ($customIcon && file_exists(getcwd() . "/" . $customIcon) ? $customIcon : null),
                'titleEmergencyLabel' => $settingRepository->getOneParamByLabel(Setting::EMERGENCY_TEXT_LABEL),
                'titleCustomLabel' => $settingRepository->getOneParamByLabel(Setting::CUSTOM_TEXT_LABEL),
                'documentSettings' => [
                    'deliveryNoteLogo' => ($deliveryNoteLogo && file_exists(getcwd() . "/uploads/attachements/" . $deliveryNoteLogo) ? $deliveryNoteLogo : null),
                    'waybillLogo' => ($waybillLogo && file_exists(getcwd() . "/uploads/attachements/" . $waybillLogo) ? $waybillLogo : null),
                ],
                'receptionSettings' => [
                    'receptionLocation' => $globalParamService->getParamLocation(Setting::DEFAULT_LOCATION_RECEPTION),
                    'listStatus' => $statusRepository->findByCategorieName(CategorieStatut::RECEPTION, 'displayOrder'),
                ],
                'deliverySettings' => [
                    'prepaAfterDl' => $settingRepository->getOneParamByLabel(Setting::CREATE_PREPA_AFTER_DL),
                    'DLAfterRecep' => $settingRepository->getOneParamByLabel(Setting::CREATE_DL_AFTER_RECEPTION),
                    'paramDemandeurLivraison' => $settingRepository->getOneParamByLabel(Setting::REQUESTER_IN_DELIVERY),
                    'displayPickingLocation' => $settingRepository->getOneParamByLabel(Setting::DISPLAY_PICKING_LOCATION),
                    'deliveryRequestTypes' => $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]),
                    'deliveryTypeSettings' => json_encode($deliveryTypeSettings),
                    'deliveryLocationDropdown' => $settingRepository->getOneParamByLabel(Setting::MANAGE_LOCATION_DELIVERY_DROPDOWN_LIST),
                ],
                'mobileConfiguration' => [
                    "skipValidationsTransferToTreat" => $settingRepository->getOneParamByLabel(Setting::TRANSFER_TO_TREAT_SKIP_VALIDATIONS),
                    "skipValidationsManualTransfer" => $settingRepository->getOneParamByLabel(Setting::MANUAL_TRANSFER_TO_TREAT_SKIP_VALIDATIONS),
                    "skipValidationsPreparations" => $settingRepository->getOneParamByLabel(Setting::PREPARATION_SKIP_VALIDATIONS),
                    "skipQuantitiesPreparations" => $settingRepository->getOneParamByLabel(Setting::PREPARATION_SKIP_QUANTITIES),
                    "skipValidationsLivraisons" => $settingRepository->getOneParamByLabel(Setting::LIVRAISON_SKIP_VALIDATIONS),
                    "skipQuantitiesLivraisons" => $settingRepository->getOneParamByLabel(Setting::LIVRAISON_SKIP_QUANTITIES),
                    "displayReferencesInTransferCard" => $settingRepository->getOneParamByLabel(Setting::TRANSFER_DISPLAY_REFERENCES_ON_CARDS),
                    "allowFreeDropTransfer" => $settingRepository->getOneParamByLabel(Setting::TRANSFER_FREE_DROP),
                    "displayArticleSelectionWithoutManual" => $settingRepository->getOneParamByLabel(Setting::PREPARATION_DISPLAY_ARTICLES_WITHOUT_MANUAL),
                ],
                'collectSetting' => [
                    'collecteLocationDropdown' => $settingRepository->getOneParamByLabel(Setting::MANAGE_LOCATION_COLLECTE_DROPDOWN_LIST),
                ],
                'arrivalSettings' => [
                    'redirect' => $settingRepository->getOneParamByLabel(Setting::REDIRECT_AFTER_NEW_ARRIVAL) ?? true,
                    'defaultArrivalsLocation' => $globalParamService->getParamLocation(Setting::MVT_DEPOSE_DESTINATION),
                    'customsArrivalsLocation' => $globalParamService->getParamLocation(Setting::DROP_OFF_LOCATION_IF_CUSTOMS),
                    'emergenciesArrivalsLocation' => $globalParamService->getParamLocation(Setting::DROP_OFF_LOCATION_IF_EMERGENCY),
                    'emergencyTriggeringFields' => $arrivalEmergencyTriggeringFieldsValue,
                    'autoPrint' => $settingRepository->getOneParamByLabel(Setting::AUTO_PRINT_COLIS),
                    'sendMail' => $settingRepository->getOneParamByLabel(Setting::SEND_MAIL_AFTER_NEW_ARRIVAL),
                    'printTwice' => $settingRepository->getOneParamByLabel(Setting::PRINT_TWICE_CUSTOMS),
                ],
                'trackingMovementsSettings' => [
                    'sendPackDeliveryRemind' => $settingRepository->getOneParamByLabel(Setting::SEND_PACK_DELIVERY_REMIND),
                ],
                'handlingSettings' => [
                    'removeHourInDatetime' => $settingRepository->getOneParamByLabel(Setting::REMOVE_HOURS_DATETIME),
                    'expectedDateColors' => [
                        'after' => $settingRepository->getOneParamByLabel(Setting::HANDLING_EXPECTED_DATE_COLOR_AFTER),
                        'DDay' => $settingRepository->getOneParamByLabel(Setting::HANDLING_EXPECTED_DATE_COLOR_D_DAY),
                        'before' => $settingRepository->getOneParamByLabel(Setting::HANDLING_EXPECTED_DATE_COLOR_BEFORE)
                    ]
                ],
                'stockSettings' => [
                    'alertThreshold' => $settingRepository->getOneParamByLabel(Setting::SEND_MAIL_MANAGER_WARNING_THRESHOLD),
                    'securityThreshold' => $settingRepository->getOneParamByLabel(Setting::SEND_MAIL_MANAGER_SECURITY_THRESHOLD),
                    'defaultLocation' => $globalParamService->getParamLocation(Setting::DEFAULT_LOCATION_REFERENCE),
                    'expirationDelay' => $settingRepository->getOneParamByLabel(Setting::STOCK_EXPIRATION_DELAY)
                ],
                'dispatchSettings' => [
                    'carrier' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_CARRIER),
                    'consignor' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_CONSIGNER),
                    'receiver' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_RECEIVER),
                    'locationFrom' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_LOCATION_FROM),
                    'locationTo' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_LOCATION_TO),
                    'waybillContactName' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_CONTACT_NAME),
                    'waybillContactPhoneMail' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_WAYBILL_CONTACT_PHONE_OR_MAIL),
                    'overconsumptionBill' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_OVERCONSUMPTION_BILL_TYPE_AND_STATUS),
                    'overconsumption_logo' => $settingRepository->getOneParamByLabel(Setting::OVERCONSUMPTION_LOGO),
                    'prefixPackCodeWithDispatchNumber' => $settingRepository->getOneParamByLabel(Setting::PREFIX_PACK_CODE_WITH_DISPATCH_NUMBER),
                    'packMustBeNew' => $settingRepository->getOneParamByLabel(Setting::PACK_MUST_BE_NEW),
                    'preFill' => $settingRepository->getOneParamByLabel(Setting::PREFILL_DUE_DATE_TODAY),
                    'statuses' => $statusRepository->findByCategorieName(CategorieStatut::DISPATCH),
                    'types' => $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_DISPATCH]),
                    'expectedDateColors' => [
                        'after' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_EXPECTED_DATE_COLOR_AFTER),
                        'DDay' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_EXPECTED_DATE_COLOR_D_DAY),
                        'before' => $settingRepository->getOneParamByLabel(Setting::DISPATCH_EXPECTED_DATE_COLOR_BEFORE)
                    ]
                ],
                'mailerServer' => $mailerServerRepository->findOneBy([]),
                'wantsBL' => $settingRepository->getOneParamByLabel(Setting::INCLUDE_BL_IN_LABEL),
                'wantsQTT' => $settingRepository->getOneParamByLabel(Setting::INCLUDE_QTT_IN_LABEL),
                'blChosen' => $settingRepository->getOneParamByLabel(Setting::CL_USED_IN_LABELS),
                'cls' => $clsForLabels,
                'translationSettings' => [
                    'translations' => $translationRepository->findAll(),
                    'menusTranslations' => array_column($translationRepository->getMenus(), '1')
                ],
                'paramCodeENC' => $settingRepository->getOneParamByLabel(Setting::USES_UTF8) ?? true,
                'encodings' => [Setting::ENCODAGE_EUW, Setting::ENCODAGE_UTF8],
                'paramCodeETQ' => $settingRepository->getOneParamByLabel(Setting::BARCODE_TYPE_IS_128) ?? true,
                'typesETQ' => [Setting::CODE_128, Setting::QR_CODE],
                'fonts' => [Setting::FONT_MONTSERRAT, Setting::FONT_TAHOMA, Setting::FONT_MYRIAD],
                'fontFamily' => $settingRepository->getOneParamByLabel(Setting::FONT_FAMILY) ?? Setting::DEFAULT_FONT_FAMILY,
                'website_logo' => ($websiteLogo && file_exists(getcwd() . "/" . $websiteLogo) ? $websiteLogo : Setting::DEFAULT_WEBSITE_LOGO_VALUE),
                'email_logo' => ($emailLogo && file_exists(getcwd() . "/" . $emailLogo) ? $emailLogo : Setting::DEFAULT_EMAIL_LOGO_VALUE),
                'mobile_logo_header' => ($mobileLogoHeader && file_exists(getcwd() . "/" . $mobileLogoHeader) ? $mobileLogoHeader : Setting::DEFAULT_MOBILE_LOGO_HEADER_VALUE),
                'mobile_logo_login' => ($mobileLogoLogin && file_exists(getcwd() . "/" . $mobileLogoLogin) ? $mobileLogoLogin : Setting::DEFAULT_MOBILE_LOGO_LOGIN_VALUE),
                'max_session_time' => $settingRepository->getOneParamByLabel(Setting::MAX_SESSION_TIME),
                'redirectMvtTraca' => $settingRepository->getOneParamByLabel(Setting::CLOSE_AND_CLEAR_AFTER_NEW_MVT),
                'workFreeDays' => $workFreeDays,
                'wantsRecipient' => $settingRepository->getOneParamByLabel(Setting::INCLUDE_RECIPIENT_IN_LABEL),
                'wantsDZLocation' => $settingRepository->getOneParamByLabel(Setting::INCLUDE_DZ_LOCATION_IN_LABEL),
                'wantsType' => $settingRepository->getOneParamByLabel(Setting::INCLUDE_ARRIVAL_TYPE_IN_LABEL),
                'wantsCustoms' => $settingRepository->getOneParamByLabel(Setting::INCLUDE_CUSTOMS_IN_LABEL),
                'wantsEmergency' => $settingRepository->getOneParamByLabel(Setting::INCLUDE_EMERGENCY_IN_LABEL),
                'wantsCommandAndProjectNumber' => $settingRepository->getOneParamByLabel(Setting::INCLUDE_COMMAND_AND_PROJECT_NUMBER_IN_LABEL),
                'wantsDestinationLocation' => $settingRepository->getOneParamByLabel(Setting::INCLUDE_DESTINATION_LOCATION_IN_ARTICLE_LABEL),
                'wantsRecipientArticle' => $settingRepository->getOneParamByLabel(Setting::INCLUDE_RECIPIENT_IN_ARTICLE_LABEL),
                'wantsDropzoneLocationArticle' => $settingRepository->getOneParamByLabel(Setting::INCLUDE_RECIPIENT_DROPZONE_LOCATION_IN_ARTICLE_LABEL),
                'wantsBatchNumberArticle' => $settingRepository->getOneParamByLabel(Setting::INCLUDE_BATCH_NUMBER_IN_ARTICLE_LABEL),
                'wantsExpirationDateArticle' => $settingRepository->getOneParamByLabel(Setting::INCLUDE_EXPIRATION_DATE_IN_ARTICLE_LABEL),
                'wantsPackCount' => $settingRepository->getOneParamByLabel(Setting::INCLUDE_PACK_COUNT_IN_LABEL),
                'currentClient' => $specificService->getAppClient(),
                'isClientChangeAllowed' => $_SERVER["APP_ENV"] === "preprod",
            ]);
    }

    /**
     * @Route("/ajax-etiquettes", name="ajax_dimensions_etiquettes",  options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::SETTINGS_GLOBAL}, mode=HasPermission::IN_JSON)
     */
    public function ajaxDimensionEtiquetteServer(Request $request,
                                                 AttachmentService $attachmentService,
                                                 EntityManagerInterface $entityManager): Response {

        $data = $request->request->all();
        $settingRepository = $entityManager->getRepository(Setting::class);

        $quantitySetting = $settingRepository->findOneBy(['label' => Setting::INCLUDE_QTT_IN_LABEL]);

        if(empty($quantitySetting)) {
            $quantitySetting = new Setting();
            $quantitySetting->setLabel(Setting::INCLUDE_QTT_IN_LABEL);
            $entityManager->persist($quantitySetting);
        }
        $quantitySetting
            ->setValue((int)($data['param-qtt-etiquette'] === 'true'));

        $settingRecipient = $settingRepository->findOneBy(['label' => Setting::INCLUDE_RECIPIENT_IN_LABEL]);

        if(empty($settingRecipient)) {
            $settingRecipient = new Setting();
            $settingRecipient->setLabel(Setting::INCLUDE_RECIPIENT_IN_LABEL);
            $entityManager->persist($settingRecipient);
        }

        $settingRecipient
            ->setValue((int)($data['param-recipient-etiquette'] === 'true'));

        $parametrageGlobalDZLocation = $settingRepository->findOneBy(['label' => Setting::INCLUDE_DZ_LOCATION_IN_LABEL]);

        if(empty($parametrageGlobalDZLocation)) {
            $parametrageGlobalDZLocation = new Setting();
            $parametrageGlobalDZLocation->setLabel(Setting::INCLUDE_DZ_LOCATION_IN_LABEL);
            $entityManager->persist($parametrageGlobalDZLocation);
        }

        $parametrageGlobalDZLocation
            ->setValue((int)($data['param-dz-location-etiquette'] === 'true'));

        $settingArrivalType = $settingRepository->findOneBy(['label' => Setting::INCLUDE_ARRIVAL_TYPE_IN_LABEL]);

        if(empty($settingArrivalType)) {
            $settingArrivalType = new Setting();
            $settingArrivalType->setLabel(Setting::INCLUDE_ARRIVAL_TYPE_IN_LABEL);
            $entityManager->persist($settingArrivalType);
        }

        $settingArrivalType
            ->setValue((int)($data['param-type-arrival-etiquette'] === 'true'));

        $settingDestinationLocation = $settingRepository->findOneBy(['label' => Setting::INCLUDE_DESTINATION_LOCATION_IN_ARTICLE_LABEL]);

        if(empty($settingDestinationLocation)) {
            $settingDestinationLocation = new Setting();
            $settingDestinationLocation->setLabel(Setting::INCLUDE_DESTINATION_LOCATION_IN_ARTICLE_LABEL);
            $entityManager->persist($settingDestinationLocation);
        }

        $settingDestinationLocation
            ->setValue((int)($data['param-add-destination-location-article-label'] === 'true'));

        $settingRecipientOnArticleLabel = $settingRepository->findOneBy(['label' => Setting::INCLUDE_RECIPIENT_IN_ARTICLE_LABEL]);

        if(empty($settingRecipientOnArticleLabel)) {
            $settingRecipientOnArticleLabel = new Setting();
            $settingRecipientOnArticleLabel->setLabel(Setting::INCLUDE_RECIPIENT_IN_ARTICLE_LABEL);
            $entityManager->persist($settingRecipientOnArticleLabel);
        }

        $settingRecipientOnArticleLabel
            ->setValue((int)($data['param-add-recipient-article-label'] === 'true'));

        $settingRecipientDropzoneOnArticleLabel = $settingRepository->findOneBy(['label' => Setting::INCLUDE_RECIPIENT_DROPZONE_LOCATION_IN_ARTICLE_LABEL]);

        if(empty($settingRecipientDropzoneOnArticleLabel)) {
            $settingRecipientDropzoneOnArticleLabel = new Setting();
            $settingRecipientDropzoneOnArticleLabel->setLabel(Setting::INCLUDE_RECIPIENT_DROPZONE_LOCATION_IN_ARTICLE_LABEL);
            $entityManager->persist($settingRecipientDropzoneOnArticleLabel);
        }

        $settingRecipientDropzoneOnArticleLabel
            ->setValue((int)($data['param-add-recipient-dropzone-location-article-label'] === 'true'));

        $settingBatchNumberOnArticleLabel = $settingRepository->findOneBy(['label' => Setting::INCLUDE_BATCH_NUMBER_IN_ARTICLE_LABEL]);

        if(empty($settingBatchNumberOnArticleLabel)) {
            $settingBatchNumberOnArticleLabel = new Setting();
            $settingBatchNumberOnArticleLabel->setLabel(Setting::INCLUDE_BATCH_NUMBER_IN_ARTICLE_LABEL);
            $entityManager->persist($settingBatchNumberOnArticleLabel);
        }

        $settingBatchNumberOnArticleLabel
            ->setValue((int)($data['param-add-batch-number-article-label'] === 'true'));

        $settingExpirationDateOnArticleLabel = $settingRepository->findOneBy(['label' => Setting::INCLUDE_EXPIRATION_DATE_IN_ARTICLE_LABEL]);

        if(empty($settingExpirationDateOnArticleLabel)) {
            $settingExpirationDateOnArticleLabel = new Setting();
            $settingExpirationDateOnArticleLabel->setLabel(Setting::INCLUDE_EXPIRATION_DATE_IN_ARTICLE_LABEL);
            $entityManager->persist($settingExpirationDateOnArticleLabel);
        }

        $settingExpirationDateOnArticleLabel
            ->setValue((int)($data['param-add-expiration-date-article-label'] === 'true'));

        $parametrageGlobalCommandAndProjectNumbers = $settingRepository->findOneBy(['label' => Setting::INCLUDE_COMMAND_AND_PROJECT_NUMBER_IN_LABEL]);

        if(empty($parametrageGlobalCommandAndProjectNumbers)) {
            $parametrageGlobalCommandAndProjectNumbers = new Setting();
            $parametrageGlobalCommandAndProjectNumbers->setLabel(Setting::INCLUDE_COMMAND_AND_PROJECT_NUMBER_IN_LABEL);
            $entityManager->persist($parametrageGlobalCommandAndProjectNumbers);
        }

        $parametrageGlobalCommandAndProjectNumbers
            ->setValue((int)($data['param-command-project-numbers-etiquette'] === 'true'));

        $parametrageGlobalPackCount = $settingRepository->findOneBy(['label' => Setting::INCLUDE_PACK_COUNT_IN_LABEL]);

        if(empty($parametrageGlobalPackCount)) {
            $parametrageGlobalPackCount = new Setting();
            $parametrageGlobalPackCount->setLabel(Setting::INCLUDE_PACK_COUNT_IN_LABEL);
            $entityManager->persist($parametrageGlobalPackCount);
        }

        $parametrageGlobalPackCount->setValue((int)($data['param-pack-count'] === 'true'));

        $parametrageGlobal = $settingRepository->findOneBy(['label' => Setting::INCLUDE_BL_IN_LABEL]);

        if(empty($parametrageGlobal)) {
            $parametrageGlobal = new Setting();
            $parametrageGlobal->setLabel(Setting::INCLUDE_BL_IN_LABEL);
            $entityManager->persist($parametrageGlobal);
        }
        $parametrageGlobal->setValue((int)($data['param-bl-etiquette'] === 'true'));

        $parametrageGlobal128 = $settingRepository->findOneBy(['label' => Setting::BARCODE_TYPE_IS_128]);

        if(empty($parametrageGlobal128)) {
            $parametrageGlobal128 = new Setting();
            $parametrageGlobal128->setLabel(Setting::BARCODE_TYPE_IS_128);
            $entityManager->persist($parametrageGlobal128);
        }
        $parametrageGlobal128->setValue($data['param-type-etiquette']);

        $parametrageGlobalCL = $settingRepository->findOneBy(['label' => Setting::CL_USED_IN_LABELS]);

        if(empty($parametrageGlobalCL)) {
            $parametrageGlobalCL = new Setting();
            $parametrageGlobalCL->setLabel(Setting::CL_USED_IN_LABELS);
            $entityManager->persist($parametrageGlobalCL);
        }
        $parametrageGlobalCL->setValue($data['param-cl-etiquette']);

        $textEmergencySetting = $settingRepository->findOneBy(['label' => Setting::EMERGENCY_TEXT_LABEL]);

        if(empty($textEmergencySetting)) {
            $textEmergencySetting = new Setting();
            $textEmergencySetting->setLabel(Setting::EMERGENCY_TEXT_LABEL);
            $entityManager->persist($textEmergencySetting);
        }
        $textEmergencySetting->setValue($data['emergency-title-label']);

        $textCustomSetting = $settingRepository->findOneBy(['label' => Setting::CUSTOM_TEXT_LABEL]);

        if(empty($textCustomSetting)) {
            $textCustomSetting = new Setting();
            $textCustomSetting->setLabel(Setting::CUSTOM_TEXT_LABEL);
            $entityManager->persist($textCustomSetting);
        }
        $textCustomSetting->setValue($data['custom-title-label']);

        $includeEmergencyInLabelSetting = $settingRepository->findOneBy(['label' => Setting::INCLUDE_EMERGENCY_IN_LABEL]);

        if(empty($includeEmergencyInLabelSetting)) {
            $includeEmergencyInLabelSetting = new Setting();
            $includeEmergencyInLabelSetting->setLabel(Setting::INCLUDE_EMERGENCY_IN_LABEL);
            $entityManager->persist($includeEmergencyInLabelSetting);
        }
        $includeEmergencyInLabelSetting->setValue((int)($data['param-emergency-etiquette'] === 'true'));

        $includeCustomInLabelSetting = $settingRepository->findOneBy(['label' => Setting::INCLUDE_CUSTOMS_IN_LABEL]);

        if(empty($includeCustomInLabelSetting)) {
            $includeCustomInLabelSetting = new Setting();
            $includeCustomInLabelSetting->setLabel(Setting::INCLUDE_CUSTOMS_IN_LABEL);
            $entityManager->persist($includeCustomInLabelSetting);
        }
        $includeCustomInLabelSetting->setValue((int)($data['param-custom-etiquette'] === 'true'));

        $parametrageGlobalLogo = $settingRepository->findOneBy(['label' => Setting::LABEL_LOGO]);

        if(!empty($request->files->all()['logo'])) {
            $fileName = $attachmentService->saveFile($request->files->all()['logo'], AttachmentService::LABEL_LOGO);
            if(empty($parametrageGlobalLogo)) {
                $parametrageGlobalLogo = new Setting();
                $parametrageGlobalLogo
                    ->setLabel(Setting::LABEL_LOGO);
                $entityManager->persist($parametrageGlobalLogo);
            }
            $parametrageGlobalLogo->setValue($fileName[array_key_first($fileName)]);
        }

        $customIconSetting = $settingRepository->findOneBy(['label' => Setting::CUSTOM_ICON]);

        if(!empty($request->files->all()['custom-icon'])) {
            $fileName = $attachmentService->saveFile($request->files->all()['custom-icon'], AttachmentService::CUSTOM_ICON);
            if(empty($customIconSetting)) {
                $customIconSetting = new Setting();
                $customIconSetting
                    ->setLabel(Setting::CUSTOM_ICON);
                $entityManager->persist($customIconSetting);
            }
            $customIconSetting->setValue('uploads/attachements/' . $fileName[array_key_first($fileName)]);
        }

        $emergencyIconSetting = $settingRepository->findOneBy(['label' => Setting::EMERGENCY_ICON]);

        if(!empty($request->files->all()['emergency-icon'])) {
            $fileName = $attachmentService->saveFile($request->files->all()['emergency-icon'], AttachmentService::EMERGENCY_ICON);
            if(empty($emergencyIconSetting)) {
                $emergencyIconSetting = new Setting();
                $emergencyIconSetting
                    ->setLabel(Setting::EMERGENCY_ICON);
                $entityManager->persist($emergencyIconSetting);
            }
            $emergencyIconSetting->setValue('uploads/attachements/' . $fileName[array_key_first($fileName)]);
        }
        $entityManager->flush();

        return new JsonResponse($data);
    }

    /**
     * @Route("/ajax-documents", name="ajax_documents",  options={"expose"=true},  methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::SETTINGS_GLOBAL}, mode=HasPermission::IN_JSON)
     */
    public function ajaxDocuments(Request $request,
                                  UserService $userService,
                                  AttachmentService $attachmentService,
                                  EntityManagerInterface $em): Response {

        $pgr = $em->getRepository(Setting::class);

        if($request->files->has("logo-delivery-note")) {
            $logo = $request->files->get("logo-delivery-note");

            $fileName = $attachmentService->saveFile($logo, AttachmentService::DELIVERY_NOTE_LOGO);
            $setting = $pgr->findOneBy(['label' => Setting::DELIVERY_NOTE_LOGO]);
            if(!$setting) {
                $setting = new Setting();
                $setting->setLabel(Setting::DELIVERY_NOTE_LOGO);
                $em->persist($setting);
            }

            $setting->setValue($fileName[array_key_first($fileName)]);
        }

        if($request->files->has("logo-waybill")) {
            $logo = $request->files->get("logo-waybill");

            $fileName = $attachmentService->saveFile($logo, AttachmentService::WAYBILL_LOGO);
            $setting = $pgr->findOneBy(['label' => Setting::WAYBILL_LOGO]);
            if(!$setting) {
                $setting = new Setting();
                $setting->setLabel(Setting::WAYBILL_LOGO);
                $em->persist($setting);
            }

            $setting->setValue($fileName[array_key_first($fileName)]);
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'msg' => 'Les paramètres ont bien été mis à jour'
        ]);
    }

    /**
     * @Route("/ajax-update-prefix-demand", name="ajax_update_prefix_demand",  options={"expose"=true},  methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function updatePrefixDemand(Request $request,
                                       EntityManagerInterface $entityManager): Response {
        if($data = json_decode($request->getContent(), true)) {
            $prefixeNomDemandeRepository = $entityManager->getRepository(PrefixeNomDemande::class);
            $prefixeDemande = $prefixeNomDemandeRepository->findOneByTypeDemande($data['typeDemande']);

            if($prefixeDemande == null) {
                $newPrefixe = new PrefixeNomDemande();
                $newPrefixe
                    ->setTypeDemandeAssociee($data['typeDemande'])
                    ->setPrefixe($data['prefixe']);

                $entityManager->persist($newPrefixe);
            } else {
                $prefixeDemande->setPrefixe($data['prefixe']);
            }
            $entityManager->flush();
            return new JsonResponse(['typeDemande' => $data['typeDemande'], 'prefixe' => $data['prefixe']]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/ajax-update-expiration-delay", name="ajax_update_expiration_delay",  options={"expose"=true},  methods="POST")
     */
    public function updateExpirationDelay(Request $request,
                                          EntityManagerInterface $manager,
                                          AlertService $service) {
        $expirationDelay = $request->request->get('expirationDelay');

        $settingRepository = $manager->getRepository(Setting::class);
        $setting = $settingRepository->findOneBy(['label' => Setting::STOCK_EXPIRATION_DELAY]);

        if(empty($setting)) {
            $setting = new Setting();
            $setting->setLabel(Setting::STOCK_EXPIRATION_DELAY);
            $manager->persist($setting);
        }

        $setting->setValue($expirationDelay);
        $manager->flush();

        $service->generateAlerts($manager);

        return $this->json([
            'success' => true
        ]);
    }

    /**
     * @Route("/ajax-get-prefix-demand", name="ajax_get_prefix_demand",  options={"expose"=true},  methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function getPrefixDemand(Request $request, EntityManagerInterface $entityManager) {
        if($data = json_decode($request->getContent(), true)) {
            $prefixeNomDemandeRepository = $entityManager->getRepository(PrefixeNomDemande::class);
            $prefixeNomDemande = $prefixeNomDemandeRepository->findOneByTypeDemande($data);

            $prefix = $prefixeNomDemande ? $prefixeNomDemande->getPrefixe() : '';

            return new JsonResponse($prefix);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/api", name="days_param_api", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::SETTINGS_GLOBAL}, mode=HasPermission::IN_JSON)
     */
    public function api(EntityManagerInterface $entityManager): Response {

        $daysWorkedRepository = $entityManager->getRepository(DaysWorked::class);

        $days = $daysWorkedRepository->findAllOrdered();
        $rows = [];
        foreach($days as $day) {
            $url['edit'] = $this->generateUrl('days_api_edit', ['id' => $day->getId()]);

            $rows[] =
                [
                    'Day' => $this->engDayToFr[$day->getDay()],
                    'Worked' => $day->isWorked() ? 'oui' : 'non',
                    'Times' => $day->getTimes() ?? '',
                    'Order' => $day->getDisplayOrder(),
                    'Actions' => $this->renderView('parametrage_global/datatableDaysRow.html.twig', [
                        'url' => $url,
                        'dayId' => $day->getId(),
                    ]),
                ];
        }
        $data['data'] = $rows;
        return new JsonResponse($data);
    }

    /**
     * @Route("/api-modifier", name="days_api_edit", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function apiEdit(Request $request, EntityManagerInterface $entityManager): Response {

        if($data = json_decode($request->getContent(), true)) {
            $daysWorkedRepository = $entityManager->getRepository(DaysWorked::class);

            $day = $daysWorkedRepository->find($data['id']);

            $json = $this->renderView('parametrage_global/modalEditDaysContent.html.twig', [
                'day' => $day,
                'dayWeek' => $this->engDayToFr[$day->getDay()]
            ]);

            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="days_edit",  options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function edit(Request $request,
                         EntityManagerInterface $entityManager): Response {

        if($data = json_decode($request->getContent(), true)) {
            $daysWorkedRepository = $entityManager->getRepository(DaysWorked::class);

            $day = $daysWorkedRepository->find($data['day']);
            $dayName = $day->getDay();

            $day->setWorked($data['worked']);

            if(isset($data['times'])) {
                if($day->isWorked()) {
                    $matchHours = '((0[0-9])|(1[0-9])|(2[0-3]))';
                    $matchMinutes = '([0-5][0-9])';
                    $matchHoursMinutes = "$matchHours:$matchMinutes";
                    $matchPeriod = "$matchHoursMinutes-$matchHoursMinutes";
                    // return 0 if it's not match or false if error
                    $resultFormat = preg_match(
                        "/^($matchPeriod(;$matchPeriod)*)?$/",
                        $data['times']
                    );

                    if(!$resultFormat) {
                        return new JsonResponse([
                            'success' => false,
                            'msg' => 'Le format des horaires est incorrect.'
                        ]);
                    }

                    $day->setTimes($data['times']);
                } else {
                    $day->setTimes(null);
                }
            }

            $entityManager->persist($day);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => 'Le jour "' . $this->engDayToFr[$dayName] . '" a bien été modifié.'
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/ajax-mail-server", name="ajax_mailer_server", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::SETTINGS_GLOBAL}, mode=HasPermission::IN_JSON)
     */
    public function ajaxMailerServer(Request $request,
                                     EntityManagerInterface $entityManager): Response {
        if($data = json_decode($request->getContent(), true)) {

            $mailerServerRepository = $entityManager->getRepository(MailerServer::class);
            $mailerServer = $mailerServerRepository->findOneBy([]);
            if(!$mailerServer) {
                $mailerServer = new MailerServer();
                $entityManager->persist($mailerServer);
            }

            $mailerServer
                ->setUser($data['user'])
                ->setPassword($data['password'])
                ->setPort($data['port'])
                ->setProtocol($data['protocol'] ?? null)
                ->setSmtp($data['smtp'])
                ->setSenderName($data['senderName'])
                ->setSenderMail($data['senderMail']);

            $entityManager->flush();

            return new JsonResponse($data);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/changer-parametres", name="toggle_params", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function toggleParams(Request $request,
                                 EntityManagerInterface $entityManager): Response {
        if($data = json_decode($request->getContent(), true)) {
            $settingRepository = $entityManager->getRepository(Setting::class);
            $ifExist = $settingRepository->findOneBy(['label' => $data['param']]);
            $em = $this->getDoctrine()->getManager();
            if($ifExist) {
                $ifExist->setValue($data['val']);
            } else {
                $parametrage = new Setting();
                $parametrage
                    ->setLabel($data['param'])
                    ->setValue($data['val']);
                $em->persist($parametrage);
            }
            $em->flush();
            return new JsonResponse(true);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/statuts-receptions", name="edit_status_receptions", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     */
    public function editStatusReceptions(Request $request,
                                         EntityManagerInterface $entityManager): Response {
        $statusRepository = $entityManager->getRepository(Statut::class);

        $statusCodes = $request->request->all();

        foreach($statusCodes as $statusId => $statusName) {
            $status = $statusRepository->find($statusId);

            if($status) {
                $status->setNom($statusName);
            }
        }
        $this->getDoctrine()->getManager()->flush();

        return new JsonResponse(true);
    }

    /**
     * @Route("/personnalisation-encodage", name="save_encodage", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     */
    public function saveEncodage(Request $request,
                                 EntityManagerInterface $entityManager): Response {

        $data = json_decode($request->getContent(), true);
        $settingRepository = $entityManager->getRepository(Setting::class);

        $parametrageGlobal = $settingRepository->findOneBy(['label' => Setting::USES_UTF8]);
        $em = $this->getDoctrine()->getManager();
        if(empty($parametrageGlobal)) {
            $parametrageGlobal = new Setting();
            $parametrageGlobal->setLabel(Setting::USES_UTF8);
            $em->persist($parametrageGlobal);
        }
        $parametrageGlobal->setValue($data);

        $em->flush();

        return new JsonResponse(true);
    }

    /**
     * @Route("/appearance", name="edit_appearance", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     */
    public function editAppearance(Request $request,
                                   KernelInterface $kernel,
                                   EntityManagerInterface $entityManager,
                                   AttachmentService $attachmentService,
                                   GlobalParamService $globalParamService): Response {

        $settingRepository = $entityManager->getRepository(Setting::class);
        $resetLogos = json_decode($request->request->get('reset-logos', '[]'), true);

        if($request->files->has("website-logo")) {
            $logo = $request->files->get("website-logo");
            $fileName = $attachmentService->saveFile($logo);
            $setting = $settingRepository->findOneBy(['label' => Setting::WEBSITE_LOGO]);
            if($settingRepository->getUnusedLogo($setting, $entityManager)){
                unlink($setting->getValue());
            }
            if(!$setting) {
                $setting = new Setting();
                $setting->setLabel(Setting::WEBSITE_LOGO);
                $entityManager->persist($setting);
            }

            $setting->setValue("uploads/attachements/" . $fileName[array_key_first($fileName)]);
        } else if(!($request->files->has("website-logo")) && ($resetLogos['website'] ?? false)) {
            $setting = $settingRepository->findOneBy(['label' => Setting::WEBSITE_LOGO]);
            $setting->setValue(Setting::DEFAULT_WEBSITE_LOGO_VALUE);
        }

        if($request->files->has("email-logo")) {
            $logo = $request->files->get("email-logo");

            $fileName = $attachmentService->saveFile($logo);
            $setting = $settingRepository->findOneBy(['label' => Setting::EMAIL_LOGO]);
            if($settingRepository->getUnusedLogo($setting, $entityManager)){
                unlink($setting->getValue());
            }
            if(!$setting) {
                $setting = new Setting();
                $setting->setLabel(Setting::EMAIL_LOGO);
                $entityManager->persist($setting);
            }

            $setting->setValue("uploads/attachements/" . $fileName[array_key_first($fileName)]);
        } else if(!($request->files->has("email-logo")) && ($resetLogos['mailLogo'] ?? false)) {
            $setting = $settingRepository->findOneBy(['label' => Setting::EMAIL_LOGO]);
            $setting->setValue(Setting::DEFAULT_EMAIL_LOGO_VALUE);
        }

        if($request->files->has("mobile-logo-login")) {
            $logo = $request->files->get("mobile-logo-login");

            $fileName = $attachmentService->saveFile($logo);
            $setting = $settingRepository->findOneBy(['label' => Setting::MOBILE_LOGO_LOGIN]);
            if($settingRepository->getUnusedLogo($setting, $entityManager)){
                unlink($setting->getValue());
            }
            if(!$setting) {
                $setting = new Setting();
                $setting->setLabel(Setting::MOBILE_LOGO_LOGIN);
                $entityManager->persist($setting);
            }

            $setting->setValue("uploads/attachements/" . $fileName[array_key_first($fileName)]);
        } else if(!($request->files->has("mobile-logo-login")) && ($resetLogos['nomadeAccueil'] ?? false)) {
            $setting = $settingRepository->findOneBy(['label' => Setting::MOBILE_LOGO_LOGIN]);
            $setting->setValue(Setting::DEFAULT_MOBILE_LOGO_LOGIN_VALUE);
        }

        if($request->files->has("mobile-logo-header")) {
            $logo = $request->files->get("mobile-logo-header");

            $fileName = $attachmentService->saveFile($logo);
            $setting = $settingRepository->findOneBy(['label' => Setting::MOBILE_LOGO_HEADER]);
            if($settingRepository->getUnusedLogo($setting, $entityManager)){
                unlink($setting->getValue());
            }
            if(!$setting) {
                $setting = new Setting();
                $setting->setLabel(Setting::MOBILE_LOGO_HEADER);
                $entityManager->persist($setting);
            }

            $setting->setValue("uploads/attachements/" . $fileName[array_key_first($fileName)]);
        } else if(!($request->files->has("mobile-logo-header")) && ($resetLogos['nomadeHeader'] ?? false)) {
            $setting = $settingRepository->findOneBy(['label' => Setting::MOBILE_LOGO_HEADER]);
            $setting->setValue(Setting::DEFAULT_MOBILE_LOGO_HEADER_VALUE);
        }

        if($request->request->has("max_session_time")) {
            if($request->request->getInt("max_session_time") > 1440) {
                return $this->json([
                    "success" => false,
                    "msg" => "Le temps maximum d'inactivité est de 24h ou 1440 minutes",
                ]);
            }

            $parametrageGlobal = $settingRepository->findOneBy(['label' => Setting::MAX_SESSION_TIME]);
            $parametrageGlobal->setValue($request->request->get("max_session_time"));
        }

        if($request->request->has("font-family")) {
            $parametrageGlobal = $settingRepository->findOneBy(['label' => Setting::FONT_FAMILY]);
            if(!$parametrageGlobal) {
                $parametrageGlobal = new Setting();
                $parametrageGlobal->setLabel(Setting::FONT_FAMILY);
                $entityManager->persist($parametrageGlobal);
            } else {
                $originalValue = $parametrageGlobal->getValue();
            }

            $parametrageGlobal->setValue($request->request->get("font-family"));

            if(isset($originalValue) && $originalValue != $request->request->get("font-family")) {
                $globalParamService->generateScssFile($parametrageGlobal);

                $env = $_SERVER["APP_ENV"] == "dev" ? "dev" : "production";
                $process = Process::fromShellCommandline("yarn build:only:$env");
                $process->run();
            }
        }

        $entityManager->flush();

        $globalParamService->generateSessionConfig();
        $globalParamService->cacheClear();

        return $this->json([
            "success" => true
        ]);
    }

    /**
     * @Route("/dispatch/overconsumption", name="edit_overconsumption_logo", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     */
    public function editOverconsumptionLogo(Request $request,
                                            EntityManagerInterface $entityManager,
                                            AttachmentService $attachmentService): Response {

        if($request->files->has("overconsumption-logo")) {
            $logo = $request->files->get("overconsumption-logo");

            $settingRepository = $entityManager->getRepository(Setting::class);

            $fileName = $attachmentService->saveFile($logo, AttachmentService::OVERCONSUMPTION_LOGO);
            $setting = $settingRepository->findOneBy(['label' => Setting::OVERCONSUMPTION_LOGO]);
            if(!$setting) {
                $setting = new Setting();
                $setting->setLabel(Setting::OVERCONSUMPTION_LOGO);
                $entityManager->persist($setting);
            }

            $setting->setValue("uploads/attachements/" . $fileName[array_key_first($fileName)]);
        }

        $entityManager->flush();

        return $this->json([
            "success" => true
        ]);
    }

    /**
     * @Route("/obtenir-encodage", name="get_encodage", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     */
    public function getEncodage(EntityManagerInterface $entityManager): Response {

        $settingRepository = $entityManager->getRepository(Setting::class);
        $parametrageGlobal = $settingRepository->findOneBy(['label' => Setting::USES_UTF8]);
        return new JsonResponse($parametrageGlobal ? $parametrageGlobal->getValue() : true);
    }

    /**
     * @Route("/obtenir-type-code", name="get_is_code_128", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     */
    public function getIsCode128(EntityManagerInterface $entityManager) {

        $settingRepository = $entityManager->getRepository(Setting::class);
        $parametrageGlobal128 = $settingRepository->findOneBy(['label' => Setting::BARCODE_TYPE_IS_128]);
        return new JsonResponse($parametrageGlobal128 ? $parametrageGlobal128->getValue() : true);
    }

    /**
     * @Route("/edit-param-locations/{label}", name="edit_param_location", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function editParamLocation(Request $request,
                                      EntityManagerInterface $entityManager,
                                      string $label): Response {
        $value = json_decode($request->getContent(), true);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $parametrage = $settingRepository->findOneBy(['label' => $label]);
        $em = $this->getDoctrine()->getManager();
        if(!$parametrage) {
            $parametrage = new Setting();
            $parametrage->setLabel($label);
            $em->persist($parametrage);
        }
        $parametrage->setValue($value);

        $em->flush();
        return new JsonResponse(true);
    }

    /**
     * @Route("/modifier-client", name="toggle_app_client", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function toggleAppClient(Request $request): Response {
        if ($data = json_decode($request->getContent(), true)) {
            $configPath = "/etc/php7/php-fpm.conf";

            //if we're not on a kubernetes pod => file doesn't exist => ignore
            if(!file_exists($configPath)) {
                return $this->json([
                    "success" => false,
                    "msg" => "Le client ne peut pas être modifié sur cette instance",
                ]);
            }

            try {
                $config = file_get_contents($configPath);
                $newAppClient = "env[APP_CLIENT] = $data";

                $config = preg_replace("/^env\[APP_CLIENT\] = .*$/mi", $newAppClient, $config);
                file_put_contents($configPath, $config);

                //magie noire qui recharge la config php fpm sur les pods kubernetes :
                //pgrep recherche l'id du processus de php fpm
                //kill envoie un message USR2 (qui veut dire "recharge la configuration") à phpfpm
                exec("kill -USR2 $(pgrep -o php-fpm7)");

                return $this->json([
                    "success" => true,
                    "msg" => "Le client de l'application a bien été modifié",
                ]);
            } catch (Exception $exception) {
                return $this->json([
                    "success" => false,
                    "msg" => "Une erreur est survenue lors du changement du client",
                ]);
            }
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/update-delivery-request-default-locations", name="update_delivery_request_default_locations", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     */
    public function updateDeliveryRequestDefaultLocations(Request $request,
                                                          EntityManagerInterface $entityManager): Response {
        if($data = json_decode($request->getContent(), true)) {
            $settingRepository = $entityManager->getRepository(Setting::class);

            $associatedTypesAndLocations = array_combine($data['types'], $data['locations']);
            $setting = $settingRepository->findOneBy(['label' => Setting::DEFAULT_LOCATION_LIVRAISON]);
            $setting->setValue(json_encode($associatedTypesAndLocations));

            $entityManager->flush();

            return $this->json([
                'success' => true
            ]);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/trigger-reminder-emails", name="trigger_reminder_emails", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     */
    public function triggerReminderEmails(EntityManagerInterface $manager, PackService $packService): Response
    {
        try {
            $packService->launchPackDeliveryReminder($manager);
            $response = [
                'success' => true,
                'msg' => "Les mails de relance ont bien été envoyés"
            ];
        } catch (Throwable $throwable) {
            $response = [
                'success' => false,
                'msg' => "Une erreur est survenue lors de l'envoi des mails de relance"
            ];
        }

        return $this->json($response);
    }
}
