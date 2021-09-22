<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategorieStatut;
use App\Entity\CategoryType;
use App\Entity\FreeField;
use App\Entity\DaysWorked;
use App\Entity\DimensionsEtiquettes;
use App\Entity\MailerServer;
use App\Entity\Menu;
use App\Entity\PrefixeNomDemande;
use App\Entity\Statut;
use App\Entity\Translation;
use App\Entity\Type;
use App\Entity\WorkFreeDay;
use App\Entity\ParametrageGlobal;

use App\Service\AlertService;
use App\Service\AttachmentService;
use App\Service\CacheService;
use App\Service\GlobalParamService;
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
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/parametrage-global")
 */
class ParametrageGlobalController extends AbstractController
{

    private $engDayToFr = [
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
     * @HasPermission({Menu::PARAM, Action::DISPLAY_GLOB})
     */
    public function index(GlobalParamService $globalParamService,
                          EntityManagerInterface $entityManager,
                          SpecificService $specificService): Response {

        $statusRepository = $entityManager->getRepository(Statut::class);
        $mailerServerRepository = $entityManager->getRepository(MailerServer::class);
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
        $dimensionsEtiquettesRepository = $entityManager->getRepository(DimensionsEtiquettes::class);
        $champsLibreRepository = $entityManager->getRepository(FreeField::class);
        $categoryCLRepository = $entityManager->getRepository(CategorieCL::class);
        $translationRepository = $entityManager->getRepository(Translation::class);
        $workFreeDaysRepository = $entityManager->getRepository(WorkFreeDay::class);

        $labelLogo = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::LABEL_LOGO);
        $emergencyIcon = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::EMERGENCY_ICON);
        $customIcon = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::CUSTOM_ICON);
        $deliveryNoteLogo = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DELIVERY_NOTE_LOGO);
        $waybillLogo = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::WAYBILL_LOGO);

        $websiteLogo = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::WEBSITE_LOGO);
        $emailLogo = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::EMAIL_LOGO);
        $mobileLogoHeader = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::MOBILE_LOGO_HEADER);
        $mobileLogoLogin = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::MOBILE_LOGO_LOGIN);

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

        return $this->render('parametrage_global/index.html.twig',
            [
                'logo' => ($labelLogo && file_exists(getcwd() . "/uploads/attachements/" . $labelLogo) ? $labelLogo : null),
                'emergencyIcon' => ($emergencyIcon && file_exists(getcwd() . "/uploads/attachements/" . $emergencyIcon) ? $emergencyIcon : null),
                'customIcon' => ($customIcon && file_exists(getcwd() . "/uploads/attachements/" . $customIcon) ? $customIcon : null),
                'titleEmergencyLabel' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::EMERGENCY_TEXT_LABEL),
                'titleCustomLabel' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::CUSTOM_TEXT_LABEL),
                'dimensions_etiquettes' => $dimensionsEtiquettesRepository->findOneDimension(),
                'documentSettings' => [
                    'deliveryNoteLogo' => ($deliveryNoteLogo && file_exists(getcwd() . "/uploads/attachements/" . $deliveryNoteLogo) ? $deliveryNoteLogo : null),
                    'waybillLogo' => ($waybillLogo && file_exists(getcwd() . "/uploads/attachements/" . $waybillLogo) ? $waybillLogo : null),
                ],
                'receptionSettings' => [
                    'receptionLocation' => $globalParamService->getParamLocation(ParametrageGlobal::DEFAULT_LOCATION_RECEPTION),
                    'listStatus' => $statusRepository->findByCategorieName(CategorieStatut::RECEPTION, 'displayOrder'),
                    'listStatusLitige' => $statusRepository->findByCategorieName(CategorieStatut::LITIGE_RECEPT)
                ],
                'deliverySettings' => [
                    'prepaAfterDl' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::CREATE_PREPA_AFTER_DL),
                    'DLAfterRecep' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::CREATE_DL_AFTER_RECEPTION),
                    'paramDemandeurLivraison' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DEMANDEUR_DANS_DL),
                    'deliveryRequestTypes' => $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_LIVRAISON]),
                    'deliveryTypeSettings' => json_encode($deliveryTypeSettings),
                    'deliveryLocationDropdown' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::MANAGE_LOCATION_DELIVERY_DROPDOWN_LIST),
                ],
                'collectSetting' => [
                    'collecteLocationDropdown' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::MANAGE_LOCATION_COLLECTE_DROPDOWN_LIST),
                ],
                'arrivalSettings' => [
                    'redirect' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::REDIRECT_AFTER_NEW_ARRIVAL) ?? true,
                    'listStatusLitige' => $statusRepository->findByCategorieName(CategorieStatut::LITIGE_ARR),
                    'defaultArrivalsLocation' => $globalParamService->getParamLocation(ParametrageGlobal::MVT_DEPOSE_DESTINATION),
                    'customsArrivalsLocation' => $globalParamService->getParamLocation(ParametrageGlobal::DROP_OFF_LOCATION_IF_CUSTOMS),
                    'emergenciesArrivalsLocation' => $globalParamService->getParamLocation(ParametrageGlobal::DROP_OFF_LOCATION_IF_EMERGENCY),
                    'emergencyTriggeringFields' => json_decode($parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::ARRIVAL_EMERGENCY_TRIGGERING_FIELDS)),
                    'autoPrint' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::AUTO_PRINT_COLIS),
                    'sendMail' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::SEND_MAIL_AFTER_NEW_ARRIVAL),
                    'printTwice' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::PRINT_TWICE_CUSTOMS),
                ],
                'handlingSettings' => [
                    'removeHourInDatetime' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::REMOVE_HOURS_DATETIME),
                    'expectedDateColors' => [
                        'after' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::HANDLING_EXPECTED_DATE_COLOR_AFTER),
                        'DDay' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::HANDLING_EXPECTED_DATE_COLOR_D_DAY),
                        'before' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::HANDLING_EXPECTED_DATE_COLOR_BEFORE)
                    ]
                ],
                'stockSettings' => [
                    'alertThreshold' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::SEND_MAIL_MANAGER_WARNING_THRESHOLD),
                    'securityThreshold' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::SEND_MAIL_MANAGER_SECURITY_THRESHOLD),
                    'expirationDelay' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::STOCK_EXPIRATION_DELAY)
                ],
                'dispatchSettings' => [
                    'carrier' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_WAYBILL_CARRIER),
                    'consignor' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_WAYBILL_CONSIGNER),
                    'receiver' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_WAYBILL_RECEIVER),
                    'locationFrom' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_WAYBILL_LOCATION_FROM),
                    'locationTo' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_WAYBILL_LOCATION_TO),
                    'waybillContactName' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_WAYBILL_CONTACT_NAME),
                    'waybillContactPhoneMail' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_WAYBILL_CONTACT_PHONE_OR_MAIL),
                    'overconsumptionBill' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_OVERCONSUMPTION_BILL_TYPE_AND_STATUS),
                    'overconsumption_logo' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::OVERCONSUMPTION_LOGO),
                    'keepModal' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::KEEP_DISPATCH_PACK_MODAL_OPEN),
                    'openModal' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::OPEN_DISPATCH_ADD_PACK_MODAL_ON_CREATION),
                    'prefixPackCodeWithDispatchNumber' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::PREFIX_PACK_CODE_WITH_DISPATCH_NUMBER),
                    'packMustBeNew' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::PACK_MUST_BE_NEW),
                    'preFill' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::PREFILL_DUE_DATE_TODAY),
                    'statuses' => $statusRepository->findByCategorieName(CategorieStatut::DISPATCH),
                    'types' => $typeRepository->findByCategoryLabels([CategoryType::DEMANDE_DISPATCH]),
                    'expectedDateColors' => [
                        'after' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_EXPECTED_DATE_COLOR_AFTER),
                        'DDay' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_EXPECTED_DATE_COLOR_D_DAY),
                        'before' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DISPATCH_EXPECTED_DATE_COLOR_BEFORE)
                    ]
                ],
                'mailerServer' => $mailerServerRepository->findOneMailerServer(),
                'wantsBL' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_BL_IN_LABEL),
                'wantsQTT' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_QTT_IN_LABEL),
                'blChosen' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::CL_USED_IN_LABELS),
                'cls' => $clsForLabels,
                'translationSettings' => [
                    'translations' => $translationRepository->findAll(),
                    'menusTranslations' => array_column($translationRepository->getMenus(), '1')
                ],
                'paramCodeENC' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::USES_UTF8) ?? true,
                'encodings' => [ParametrageGlobal::ENCODAGE_EUW, ParametrageGlobal::ENCODAGE_UTF8],
                'paramCodeETQ' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::BARCODE_TYPE_IS_128) ?? true,
                'typesETQ' => [ParametrageGlobal::CODE_128, ParametrageGlobal::QR_CODE],
                'fonts' => [ParametrageGlobal::FONT_MONTSERRAT, ParametrageGlobal::FONT_TAHOMA, ParametrageGlobal::FONT_MYRIAD],
                'fontFamily' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::FONT_FAMILY) ?? ParametrageGlobal::DEFAULT_FONT_FAMILY,
                'website_logo' => ($websiteLogo && file_exists(getcwd() . "/" . $websiteLogo) ? $websiteLogo : ParametrageGlobal::DEFAULT_WEBSITE_LOGO_VALUE),
                'email_logo' => ($emailLogo && file_exists(getcwd() . "/" . $emailLogo) ? $emailLogo : ParametrageGlobal::DEFAULT_EMAIL_LOGO_VALUE),
                'mobile_logo_header' => ($mobileLogoHeader && file_exists(getcwd() . "/" . $mobileLogoHeader) ? $mobileLogoHeader : ParametrageGlobal::DEFAULT_MOBILE_LOGO_HEADER_VALUE),
                'mobile_logo_login' => ($mobileLogoLogin && file_exists(getcwd() . "/" . $mobileLogoLogin) ? $mobileLogoLogin : ParametrageGlobal::DEFAULT_MOBILE_LOGO_LOGIN_VALUE),
                'redirectMvtTraca' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::CLOSE_AND_CLEAR_AFTER_NEW_MVT),
                'workFreeDays' => $workFreeDays,
                'wantsRecipient' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_RECIPIENT_IN_LABEL),
                'wantsDZLocation' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_DZ_LOCATION_IN_LABEL),
                'wantsType' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_ARRIVAL_TYPE_IN_LABEL),
                'wantsCustoms' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_CUSTOMS_IN_LABEL),
                'wantsEmergency' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_EMERGENCY_IN_LABEL),
                'wantsCommandAndProjectNumber' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_COMMAND_AND_PROJECT_NUMBER_IN_LABEL),
                'wantsDestinationLocation' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_DESTINATION_LOCATION_IN_ARTICLE_LABEL),
                'wantsRecipientArticle' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_RECIPIENT_IN_ARTICLE_LABEL),
                'wantsDropzoneLocationArticle' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_RECIPIENT_DROPZONE_LOCATION_IN_ARTICLE_LABEL),
                'wantsBatchNumberArticle' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_BATCH_NUMBER_IN_ARTICLE_LABEL),
                'wantsExpirationDateArticle' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_EXPIRATION_DATE_IN_ARTICLE_LABEL),
                'wantsPackCount' => $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::INCLUDE_PACK_COUNT_IN_LABEL),
                'currentClient' => $specificService->getAppClient(),
                'isClientChangeAllowed' => $_SERVER["APP_ENV"] === "preprod"
            ]);
    }

    /**
     * @Route("/ajax-etiquettes", name="ajax_dimensions_etiquettes",  options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_GLOB}, mode=HasPermission::IN_JSON)
     */
    public function ajaxDimensionEtiquetteServer(Request $request,
                                                 AttachmentService $attachmentService,
                                                 EntityManagerInterface $entityManager): Response {

        $data = $request->request->all();
        $dimensionsEtiquettesRepository = $entityManager->getRepository(DimensionsEtiquettes::class);
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);

        $dimensions = $dimensionsEtiquettesRepository->findOneDimension();
        if(!$dimensions) {
            $dimensions = new DimensionsEtiquettes();
            $entityManager->persist($dimensions);
        }
        $dimensions
            ->setHeight(intval($data['height']))
            ->setWidth(intval($data['width']));

        $parametrageGlobalQtt = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::INCLUDE_QTT_IN_LABEL]);

        if(empty($parametrageGlobalQtt)) {
            $parametrageGlobalQtt = new ParametrageGlobal();
            $parametrageGlobalQtt->setLabel(ParametrageGlobal::INCLUDE_QTT_IN_LABEL);
            $entityManager->persist($parametrageGlobalQtt);
        }
        $parametrageGlobalQtt
            ->setValue((int)($data['param-qtt-etiquette'] === 'true'));

        $parametrageGlobalRecipient = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::INCLUDE_RECIPIENT_IN_LABEL]);

        if(empty($parametrageGlobalRecipient)) {
            $parametrageGlobalRecipient = new ParametrageGlobal();
            $parametrageGlobalRecipient->setLabel(ParametrageGlobal::INCLUDE_RECIPIENT_IN_LABEL);
            $entityManager->persist($parametrageGlobalRecipient);
        }

        $parametrageGlobalRecipient
            ->setValue((int)($data['param-recipient-etiquette'] === 'true'));

        $parametrageGlobalDZLocation = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::INCLUDE_DZ_LOCATION_IN_LABEL]);

        if(empty($parametrageGlobalDZLocation)) {
            $parametrageGlobalDZLocation = new ParametrageGlobal();
            $parametrageGlobalDZLocation->setLabel(ParametrageGlobal::INCLUDE_DZ_LOCATION_IN_LABEL);
            $entityManager->persist($parametrageGlobalDZLocation);
        }

        $parametrageGlobalDZLocation
            ->setValue((int)($data['param-dz-location-etiquette'] === 'true'));

        $parametrageGlobalArrivalType = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::INCLUDE_ARRIVAL_TYPE_IN_LABEL]);

        if(empty($parametrageGlobalArrivalType)) {
            $parametrageGlobalArrivalType = new ParametrageGlobal();
            $parametrageGlobalArrivalType->setLabel(ParametrageGlobal::INCLUDE_ARRIVAL_TYPE_IN_LABEL);
            $entityManager->persist($parametrageGlobalArrivalType);
        }

        $parametrageGlobalArrivalType
            ->setValue((int)($data['param-type-arrival-etiquette'] === 'true'));

        $globalSettingsDestinationLocation = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::INCLUDE_DESTINATION_LOCATION_IN_ARTICLE_LABEL]);

        if(empty($globalSettingsDestinationLocation)) {
            $globalSettingsDestinationLocation = new ParametrageGlobal();
            $globalSettingsDestinationLocation->setLabel(ParametrageGlobal::INCLUDE_DESTINATION_LOCATION_IN_ARTICLE_LABEL);
            $entityManager->persist($globalSettingsDestinationLocation);
        }

        $globalSettingsDestinationLocation
            ->setValue((int)($data['param-add-destination-location-article-label'] === 'true'));

        $globalSettingsRecipientOnArticleLabel = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::INCLUDE_RECIPIENT_IN_ARTICLE_LABEL]);

        if(empty($globalSettingsRecipientOnArticleLabel)) {
            $globalSettingsRecipientOnArticleLabel = new ParametrageGlobal();
            $globalSettingsRecipientOnArticleLabel->setLabel(ParametrageGlobal::INCLUDE_RECIPIENT_IN_ARTICLE_LABEL);
            $entityManager->persist($globalSettingsRecipientOnArticleLabel);
        }

        $globalSettingsRecipientOnArticleLabel
            ->setValue((int)($data['param-add-recipient-article-label'] === 'true'));

        $globalSettingsRecipientDropzoneOnArticleLabel = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::INCLUDE_RECIPIENT_DROPZONE_LOCATION_IN_ARTICLE_LABEL]);

        if(empty($globalSettingsRecipientDropzoneOnArticleLabel)) {
            $globalSettingsRecipientDropzoneOnArticleLabel = new ParametrageGlobal();
            $globalSettingsRecipientDropzoneOnArticleLabel->setLabel(ParametrageGlobal::INCLUDE_RECIPIENT_DROPZONE_LOCATION_IN_ARTICLE_LABEL);
            $entityManager->persist($globalSettingsRecipientDropzoneOnArticleLabel);
        }

        $globalSettingsRecipientDropzoneOnArticleLabel
            ->setValue((int)($data['param-add-recipient-dropzone-location-article-label'] === 'true'));

        $globalSettingsBatchNumberOnArticleLabel = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::INCLUDE_BATCH_NUMBER_IN_ARTICLE_LABEL]);

        if(empty($globalSettingsBatchNumberOnArticleLabel)) {
            $globalSettingsBatchNumberOnArticleLabel = new ParametrageGlobal();
            $globalSettingsBatchNumberOnArticleLabel->setLabel(ParametrageGlobal::INCLUDE_BATCH_NUMBER_IN_ARTICLE_LABEL);
            $entityManager->persist($globalSettingsBatchNumberOnArticleLabel);
        }

        $globalSettingsBatchNumberOnArticleLabel
            ->setValue((int)($data['param-add-batch-number-article-label'] === 'true'));

        $globalSettingsExpirationDateOnArticleLabel = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::INCLUDE_EXPIRATION_DATE_IN_ARTICLE_LABEL]);

        if(empty($globalSettingsExpirationDateOnArticleLabel)) {
            $globalSettingsExpirationDateOnArticleLabel = new ParametrageGlobal();
            $globalSettingsExpirationDateOnArticleLabel->setLabel(ParametrageGlobal::INCLUDE_EXPIRATION_DATE_IN_ARTICLE_LABEL);
            $entityManager->persist($globalSettingsExpirationDateOnArticleLabel);
        }

        $globalSettingsExpirationDateOnArticleLabel
            ->setValue((int)($data['param-add-expiration-date-article-label'] === 'true'));

        $parametrageGlobalCommandAndProjectNumbers = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::INCLUDE_COMMAND_AND_PROJECT_NUMBER_IN_LABEL]);

        if(empty($parametrageGlobalCommandAndProjectNumbers)) {
            $parametrageGlobalCommandAndProjectNumbers = new ParametrageGlobal();
            $parametrageGlobalCommandAndProjectNumbers->setLabel(ParametrageGlobal::INCLUDE_COMMAND_AND_PROJECT_NUMBER_IN_LABEL);
            $entityManager->persist($parametrageGlobalCommandAndProjectNumbers);
        }

        $parametrageGlobalCommandAndProjectNumbers
            ->setValue((int)($data['param-command-project-numbers-etiquette'] === 'true'));

        $parametrageGlobalPackCount = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::INCLUDE_PACK_COUNT_IN_LABEL]);

        if(empty($parametrageGlobalPackCount)) {
            $parametrageGlobalPackCount = new ParametrageGlobal();
            $parametrageGlobalPackCount->setLabel(ParametrageGlobal::INCLUDE_PACK_COUNT_IN_LABEL);
            $entityManager->persist($parametrageGlobalPackCount);
        }

        $parametrageGlobalPackCount->setValue((int)($data['param-pack-count'] === 'true'));

        $parametrageGlobal = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::INCLUDE_BL_IN_LABEL]);

        if(empty($parametrageGlobal)) {
            $parametrageGlobal = new ParametrageGlobal();
            $parametrageGlobal->setLabel(ParametrageGlobal::INCLUDE_BL_IN_LABEL);
            $entityManager->persist($parametrageGlobal);
        }
        $parametrageGlobal->setValue((int)($data['param-bl-etiquette'] === 'true'));

        $parametrageGlobal128 = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::BARCODE_TYPE_IS_128]);

        if(empty($parametrageGlobal128)) {
            $parametrageGlobal128 = new ParametrageGlobal();
            $parametrageGlobal128->setLabel(ParametrageGlobal::BARCODE_TYPE_IS_128);
            $entityManager->persist($parametrageGlobal128);
        }
        $parametrageGlobal128->setValue($data['param-type-etiquette']);

        $parametrageGlobalCL = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::CL_USED_IN_LABELS]);

        if(empty($parametrageGlobalCL)) {
            $parametrageGlobalCL = new ParametrageGlobal();
            $parametrageGlobalCL->setLabel(ParametrageGlobal::CL_USED_IN_LABELS);
            $entityManager->persist($parametrageGlobalCL);
        }
        $parametrageGlobalCL->setValue($data['param-cl-etiquette']);

        $textEmergencyGlobalSettings = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::EMERGENCY_TEXT_LABEL]);

        if(empty($textEmergencyGlobalSettings)) {
            $textEmergencyGlobalSettings = new ParametrageGlobal();
            $textEmergencyGlobalSettings->setLabel(ParametrageGlobal::EMERGENCY_TEXT_LABEL);
            $entityManager->persist($textEmergencyGlobalSettings);
        }
        $textEmergencyGlobalSettings->setValue($data['emergency-title-label']);

        $textCustomGlobalSettings = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::CUSTOM_TEXT_LABEL]);

        if(empty($textCustomGlobalSettings)) {
            $textCustomGlobalSettings = new ParametrageGlobal();
            $textCustomGlobalSettings->setLabel(ParametrageGlobal::CUSTOM_TEXT_LABEL);
            $entityManager->persist($textCustomGlobalSettings);
        }
        $textCustomGlobalSettings->setValue($data['custom-title-label']);

        $includeEmergencyInLabelGlobalSettings = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::INCLUDE_EMERGENCY_IN_LABEL]);

        if(empty($includeEmergencyInLabelGlobalSettings)) {
            $includeEmergencyInLabelGlobalSettings = new ParametrageGlobal();
            $includeEmergencyInLabelGlobalSettings->setLabel(ParametrageGlobal::INCLUDE_EMERGENCY_IN_LABEL);
            $entityManager->persist($includeEmergencyInLabelGlobalSettings);
        }
        $includeEmergencyInLabelGlobalSettings->setValue((int)($data['param-emergency-etiquette'] === 'true'));

        $includeCustomInLabelGlobalSettings = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::INCLUDE_CUSTOMS_IN_LABEL]);

        if(empty($includeCustomInLabelGlobalSettings)) {
            $includeCustomInLabelGlobalSettings = new ParametrageGlobal();
            $includeCustomInLabelGlobalSettings->setLabel(ParametrageGlobal::INCLUDE_CUSTOMS_IN_LABEL);
            $entityManager->persist($includeCustomInLabelGlobalSettings);
        }
        $includeCustomInLabelGlobalSettings->setValue((int)($data['param-custom-etiquette'] === 'true'));

        $parametrageGlobalLogo = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::LABEL_LOGO]);

        if(!empty($request->files->all()['logo'])) {
            $fileName = $attachmentService->saveFile($request->files->all()['logo'], AttachmentService::LABEL_LOGO);
            if(empty($parametrageGlobalLogo)) {
                $parametrageGlobalLogo = new ParametrageGlobal();
                $parametrageGlobalLogo
                    ->setLabel(ParametrageGlobal::LABEL_LOGO);
                $entityManager->persist($parametrageGlobalLogo);
            }
            $parametrageGlobalLogo->setValue($fileName[array_key_first($fileName)]);
        }

        $customIconGlobalSettings = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::CUSTOM_ICON]);

        if(!empty($request->files->all()['custom-icon'])) {
            $fileName = $attachmentService->saveFile($request->files->all()['custom-icon'], AttachmentService::CUSTOM_ICON);
            if(empty($customIconGlobalSettings)) {
                $customIconGlobalSettings = new ParametrageGlobal();
                $customIconGlobalSettings
                    ->setLabel(ParametrageGlobal::CUSTOM_ICON);
                $entityManager->persist($customIconGlobalSettings);
            }
            $customIconGlobalSettings->setValue($fileName[array_key_first($fileName)]);
        }

        $emergencyIconGlobalSettings = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::EMERGENCY_ICON]);

        if(!empty($request->files->all()['emergency-icon'])) {
            $fileName = $attachmentService->saveFile($request->files->all()['emergency-icon'], AttachmentService::EMERGENCY_ICON);
            if(empty($emergencyIconGlobalSettings)) {
                $emergencyIconGlobalSettings = new ParametrageGlobal();
                $emergencyIconGlobalSettings
                    ->setLabel(ParametrageGlobal::EMERGENCY_ICON);
                $entityManager->persist($emergencyIconGlobalSettings);
            }
            $emergencyIconGlobalSettings->setValue($fileName[array_key_first($fileName)]);
        }
        $entityManager->flush();

        return new JsonResponse($data);
    }

    /**
     * @Route("/ajax-documents", name="ajax_documents",  options={"expose"=true},  methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::DISPLAY_GLOB}, mode=HasPermission::IN_JSON)
     */
    public function ajaxDocuments(Request $request,
                                  UserService $userService,
                                  AttachmentService $attachmentService,
                                  EntityManagerInterface $em): Response {

        $pgr = $em->getRepository(ParametrageGlobal::class);

        if($request->files->has("logo-delivery-note")) {
            $logo = $request->files->get("logo-delivery-note");

            $fileName = $attachmentService->saveFile($logo, AttachmentService::DELIVERY_NOTE_LOGO);
            $setting = $pgr->findOneBy(['label' => ParametrageGlobal::DELIVERY_NOTE_LOGO]);
            if(!$setting) {
                $setting = new ParametrageGlobal();
                $setting->setLabel(ParametrageGlobal::DELIVERY_NOTE_LOGO);
                $em->persist($setting);
            }

            $setting->setValue($fileName[array_key_first($fileName)]);
        }

        if($request->files->has("logo-waybill")) {
            $logo = $request->files->get("logo-waybill");

            $fileName = $attachmentService->saveFile($logo, AttachmentService::WAYBILL_LOGO);
            $setting = $pgr->findOneBy(['label' => ParametrageGlobal::WAYBILL_LOGO]);
            if(!$setting) {
                $setting = new ParametrageGlobal();
                $setting->setLabel(ParametrageGlobal::WAYBILL_LOGO);
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

        $parametrageGlobalRepository = $manager->getRepository(ParametrageGlobal::class);
        $setting = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::STOCK_EXPIRATION_DELAY]);

        if(empty($setting)) {
            $setting = new ParametrageGlobal();
            $setting->setLabel(ParametrageGlobal::STOCK_EXPIRATION_DELAY);
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
     * @HasPermission({Menu::PARAM, Action::DISPLAY_GLOB}, mode=HasPermission::IN_JSON)
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
                    'Worked' => $day->getWorked() ? 'oui' : 'non',
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
                if($day->getWorked()) {
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
     * @HasPermission({Menu::PARAM, Action::DISPLAY_GLOB}, mode=HasPermission::IN_JSON)
     */
    public function ajaxMailerServer(Request $request,
                                     EntityManagerInterface $entityManager): Response {
        if($data = json_decode($request->getContent(), true)) {

            $mailerServerRepository = $entityManager->getRepository(MailerServer::class);
            $mailerServer = $mailerServerRepository->findOneMailerServer();
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
            $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
            $ifExist = $parametrageGlobalRepository->findOneBy(['label' => $data['param']]);
            $em = $this->getDoctrine()->getManager();
            if($ifExist) {
                $ifExist->setValue($data['val']);
            } else {
                $parametrage = new ParametrageGlobal();
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
     * @Route("/personnalisation", name="save_translations", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     */
    public function saveTranslations(Request $request,
                                     EntityManagerInterface $entityManager,
                                     TranslationService $translationService,
                                        CacheService $cacheService): Response {
        if($translations = json_decode($request->getContent(), true)) {
            $translationRepository = $entityManager->getRepository(Translation::class);
            foreach($translations as $translation) {
                $translationObject = $translationRepository->find($translation['id']);
                if($translationObject) {
                    $translationObject
                        ->setTranslation($translation['val'] ?: null)
                        ->setUpdated(1);
                } else {
                    return new JsonResponse(false);
                }
            }
            $entityManager->flush();

            $cacheService->clear();
            $translationService->generateTranslationsFile();
            $translationService->cacheClearWarmUp();

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
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);

        $parametrageGlobal = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::USES_UTF8]);
        $em = $this->getDoctrine()->getManager();
        if(empty($parametrageGlobal)) {
            $parametrageGlobal = new ParametrageGlobal();
            $parametrageGlobal->setLabel(ParametrageGlobal::USES_UTF8);
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
                                   EntityManagerInterface $entityManager,
                                   AttachmentService $attachmentService,
                                   GlobalParamService $globalParamService): Response {

        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
        $resetLogos = json_decode($request->request->get('reset-logos', '[]'), true);

        if($request->files->has("website-logo")) {
            $logo = $request->files->get("website-logo");
            $fileName = $attachmentService->saveFile($logo);
            $setting = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::WEBSITE_LOGO]);
            if($parametrageGlobalRepository->getUnusedLogo($setting, $entityManager)){
                unlink($setting->getValue());
            }
            if(!$setting) {
                $setting = new ParametrageGlobal();
                $setting->setLabel(ParametrageGlobal::WEBSITE_LOGO);
                $entityManager->persist($setting);
            }

            $setting->setValue("uploads/attachements/" . $fileName[array_key_first($fileName)]);
        } else if(!($request->files->has("website-logo")) && ($resetLogos['website'] ?? false)) {
            $setting = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::WEBSITE_LOGO]);
            $setting->setValue(ParametrageGlobal::DEFAULT_WEBSITE_LOGO_VALUE);
        }

        if($request->files->has("email-logo")) {
            $logo = $request->files->get("email-logo");

            $fileName = $attachmentService->saveFile($logo);
            $setting = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::EMAIL_LOGO]);
            if($parametrageGlobalRepository->getUnusedLogo($setting, $entityManager)){
                unlink($setting->getValue());
            }
            if(!$setting) {
                $setting = new ParametrageGlobal();
                $setting->setLabel(ParametrageGlobal::EMAIL_LOGO);
                $entityManager->persist($setting);
            }

            $setting->setValue("uploads/attachements/" . $fileName[array_key_first($fileName)]);
        } else if(!($request->files->has("email-logo")) && ($resetLogos['mailLogo'] ?? false)) {
            $setting = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::EMAIL_LOGO]);
            $setting->setValue(ParametrageGlobal::DEFAULT_EMAIL_LOGO_VALUE);
        }

        if($request->files->has("mobile-logo-login")) {
            $logo = $request->files->get("mobile-logo-login");

            $fileName = $attachmentService->saveFile($logo);
            $setting = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::MOBILE_LOGO_LOGIN]);
            if($parametrageGlobalRepository->getUnusedLogo($setting, $entityManager)){
                unlink($setting->getValue());
            }
            if(!$setting) {
                $setting = new ParametrageGlobal();
                $setting->setLabel(ParametrageGlobal::MOBILE_LOGO_LOGIN);
                $entityManager->persist($setting);
            }

            $setting->setValue("uploads/attachements/" . $fileName[array_key_first($fileName)]);
        } else if(!($request->files->has("mobile-logo-login")) && ($resetLogos['nomadeAccueil'] ?? false)) {
            $setting = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::MOBILE_LOGO_LOGIN]);
            $setting->setValue(ParametrageGlobal::DEFAULT_MOBILE_LOGO_LOGIN_VALUE);
        }

        if($request->files->has("mobile-logo-header")) {
            $logo = $request->files->get("mobile-logo-header");

            $fileName = $attachmentService->saveFile($logo);
            $setting = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::MOBILE_LOGO_HEADER]);
            if($parametrageGlobalRepository->getUnusedLogo($setting, $entityManager)){
                unlink($setting->getValue());
            }
            if(!$setting) {
                $setting = new ParametrageGlobal();
                $setting->setLabel(ParametrageGlobal::MOBILE_LOGO_HEADER);
                $entityManager->persist($setting);
            }

            $setting->setValue("uploads/attachements/" . $fileName[array_key_first($fileName)]);
        } else if(!($request->files->has("mobile-logo-header")) && ($resetLogos['nomadeHeader'] ?? false)) {
            $setting = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::MOBILE_LOGO_HEADER]);
            $setting->setValue(ParametrageGlobal::DEFAULT_MOBILE_LOGO_HEADER_VALUE);
        }

        if($request->request->has("font-family")) {
            $parametrageGlobal = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::FONT_FAMILY]);
            if(!$parametrageGlobal) {
                $parametrageGlobal = new ParametrageGlobal();
                $parametrageGlobal->setLabel(ParametrageGlobal::FONT_FAMILY);
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

            $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);

            $fileName = $attachmentService->saveFile($logo, AttachmentService::OVERCONSUMPTION_LOGO);
            $setting = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::OVERCONSUMPTION_LOGO]);
            if(!$setting) {
                $setting = new ParametrageGlobal();
                $setting->setLabel(ParametrageGlobal::OVERCONSUMPTION_LOGO);
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

        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
        $parametrageGlobal = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::USES_UTF8]);
        return new JsonResponse($parametrageGlobal ? $parametrageGlobal->getValue() : true);
    }

    /**
     * @Route("/obtenir-type-code", name="get_is_code_128", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     */
    public function getIsCode128(EntityManagerInterface $entityManager) {

        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
        $parametrageGlobal128 = $parametrageGlobalRepository->findOneBy(['label' => ParametrageGlobal::BARCODE_TYPE_IS_128]);
        return new JsonResponse($parametrageGlobal128 ? $parametrageGlobal128->getValue() : true);
    }

    /**
     * @Route("/edit-param-locations/{label}", name="edit_param_location", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function editParamLocation(Request $request,
                                      EntityManagerInterface $entityManager,
                                      string $label): Response {
        $value = json_decode($request->getContent(), true);
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
        $parametrage = $parametrageGlobalRepository->findOneBy(['label' => $label]);
        $em = $this->getDoctrine()->getManager();
        if(!$parametrage) {
            $parametrage = new ParametrageGlobal();
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
                //kill envoie un message USER2 (qui veut dire "recharge la configuration") à phpfpm
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
            $globalSettingsRepository = $entityManager->getRepository(ParametrageGlobal::class);

            $associatedTypesAndLocations = array_combine($data['types'], $data['locations']);
            $deliveryRequestDefaultLocations = $globalSettingsRepository->findOneBy(['label' => ParametrageGlobal::DEFAULT_LOCATION_LIVRAISON]);
            $deliveryRequestDefaultLocations->setValue(json_encode($associatedTypesAndLocations));

            $entityManager->flush();

            return $this->json([
                'success' => true
            ]);
        }
        throw new BadRequestHttpException();
    }
}
