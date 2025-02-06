<?php

namespace App\DataFixtures;

use App\Entity\Fields\FixedFieldByType;
use App\Entity\Fields\FixedFieldEnum;
use App\Entity\Fields\FixedFieldStandard;
use App\Entity\FreeField\FreeField;
use App\Entity\Setting;
use App\Service\SpecificService;
use App\Service\UniqueNumberService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Contracts\Service\Attribute\Required;

class SettingFixtures extends Fixture implements FixtureGroupInterface {

    #[Required]
    public SpecificService $specificService;

    private const FORCE_RESET_CONSTANTS = [
        Setting::DEFAULT_DELIVERY_WAYBILL_TEMPLATE,
        Setting::DEFAULT_DISPATCH_WAYBILL_TEMPLATE,
        Setting::DEFAULT_DISPATCH_WAYBILL_TEMPLATE_WITH_RUPTURE,
        Setting::DEFAULT_DISPATCH_RECAP_TEMPLATE,
        Setting::DEFAULT_DISPATCH_SHIPMENT_NOTE,
        Setting::DEFAULT_DELIVERY_SLIP_TEMPLATE,
        Setting::DEFAULT_PURCHASE_ORDER_TEMPLATE,
    ];

    public function load(ObjectManager $manager) {
        $output = new ConsoleOutput();

        $parametreGlobalRepository = $manager->getRepository(Setting::class);
        $fixedFieldByTypeRepository = $manager->getRepository(FixedFieldByType::class);

        $globalParameterLabels = [
            Setting::MAX_SESSION_TIME => [
                'default' => 30,
            ],
            Setting::APP_CLIENT_LABEL => [
                'default' => SpecificService::DEFAULT_CLIENT_LABEL,
            ],
            Setting::CREATE_DL_AFTER_RECEPTION => [
                'default' => false,
                SpecificService::CLIENT_BOURGUIGNON => true,
                SpecificService::CLIENT_BARBECUE => true
            ],
            Setting::CREATE_PREPA_AFTER_DL => [
                'default' => true,
            ],
            Setting::CREATE_DELIVERY_ONLY => [
                'default' => false,
            ],
            Setting::DIRECT_DELIVERY => [
                'default' => false,
                SpecificService::CLIENT_GRATIN_DAUPHINOIS => true
            ],
            Setting::MANAGE_LOCATION_DELIVERY_DROPDOWN_LIST => [
                'default' => false,
                SpecificService::CLIENT_PETIT_SALE => true,
            ],
            Setting::SET_PREPARED_UPON_DELIVERY_VALIDATION => [
                'default' => false,
            ],
            Setting::MANAGE_PREPARATIONS_WITH_PLANNING => [
                'default' => false,
            ],
            Setting::MANAGE_DELIVERIES_WITHOUT_STOCK_QUANTITY => [
                'default' => false,
            ],
            Setting::MANAGE_LOCATION_COLLECTE_DROPDOWN_LIST => [
                'default' => false,
                SpecificService::CLIENT_PETIT_SALE => true,
            ],
            Setting::INCLUDE_BL_IN_LABEL => [
                'default' => false,
                SpecificService::CLIENT_BOURGUIGNON => true,
                SpecificService::CLIENT_BARBECUE => true
            ],
            Setting::REDIRECT_AFTER_NEW_ARRIVAL => [
                'default' => true,
                SpecificService::CLIENT_PETIT_SALE => false
            ],
            Setting::SEND_MAIL_AFTER_NEW_ARRIVAL => [
                'default' => false,
                SpecificService::CLIENT_PETIT_SALE => true
            ],
            Setting::INCLUDE_DZ_LOCATION_IN_LABEL => [
                'default' => true,
            ],
            Setting::INCLUDE_ARRIVAL_TYPE_IN_LABEL => [
                'default' => true,
            ],
            setting::INCLUDE_BUSINESS_UNIT_IN_LABEL => [
                'default' => false,
                SpecificService::CLIENT_ALIGOT => true,
            ],
            setting::INCLUDE_PROJECT_IN_LABEL => [
                'default' => false,
            ],
            setting::INCLUDE_SHOW_DATE_AND_HOUR_ARRIVAL_UL => [
                'default' => false,
            ],
            setting::INCLUDE_TYPE_LOGO_ON_TAG => [
                'default' => false,
            ],
            Setting::INCLUDE_EMERGENCY_IN_LABEL => [
                'default' => false,
            ],
            Setting::INCLUDE_CUSTOMS_IN_LABEL => [
                'default' => false,
            ],
            Setting::INCLUDE_PACK_COUNT_IN_LABEL => [
                'default' => true,
            ],
            Setting::INCLUDE_RECIPIENT_IN_LABEL => [
                'default' => false,
            ],
            Setting::INCLUDE_COMMAND_AND_PROJECT_NUMBER_IN_LABEL => [
                'default' => true,
            ],
            Setting::INCLUDE_DESTINATION_LOCATION_IN_ARTICLE_LABEL => [
                'default' => false,
            ],
            Setting::INCLUDE_RECIPIENT_IN_ARTICLE_LABEL => [
                'default' => false,
            ],
            Setting::INCLUDE_RECIPIENT_DROPZONE_LOCATION_IN_ARTICLE_LABEL => [
                'default' => false,
            ],
            Setting::INCLUDE_BATCH_NUMBER_IN_ARTICLE_LABEL => [
                'default' => false,
            ],
            Setting::INCLUDE_EXPIRATION_DATE_IN_ARTICLE_LABEL => [
                'default' => false,
            ],
            Setting::INCLUDE_STOCK_ENTRY_DATE_IN_ARTICLE_LABEL => [
                'default' => false,
            ],
            Setting::DISPATCH_WAYBILL_CONTACT_PHONE_OR_MAIL => [
                'default' => null,
            ],
            Setting::DISPATCH_WAYBILL_CONTACT_NAME => [
                'default' => null,
            ],
            Setting::AUTO_PRINT_LU => [
                'default' => true,
            ],
            Setting::DISPATCH_NEW_REFERENCE_TYPE => [
                'default' => null,
            ],
            Setting::DISPATCH_NEW_REFERENCE_STATUS => [
                'default' => null,
            ],
            Setting::DISPATCH_NEW_REFERENCE_QUANTITY_MANAGEMENT => [
                'default' => null,
            ],
            Setting::USE_TRUCK_ARRIVALS => [
                'default' => false,
            ],
            Setting::SEND_MAIL_MANAGER_WARNING_THRESHOLD => [
                'default' => false,
                SpecificService::CLIENT_GRATIN_DAUPHINOIS => true
            ],
            Setting::SEND_MAIL_MANAGER_SECURITY_THRESHOLD => [
                'default' => false,
                SpecificService::CLIENT_GRATIN_DAUPHINOIS => true
            ],
            Setting::STOCK_EXPIRATION_DELAY => [],
            Setting::REFERENCE_ARTICLE_ASSOCIATED_DOCUMENT_TYPE_VALUES => [
                "default" => false,
                SpecificService::CLIENT_GALETTE_SAUCISSE => implode(",", ["FME", "JAM", "CC", "Autres"])
            ],
            Setting::CL_USED_IN_LABELS => [
                'default' => FreeField::SPECIC_COLLINS_BL
            ],
            Setting::CLEAR_AND_KEEP_MODAL_AFTER_NEW_MVT => [
                'default' => true,
                SpecificService::CLIENT_PETIT_SALE => false
            ],
            Setting::DISPLAY_WARNING_WRONG_LOCATION => [
                'default' => false,
            ],
            Setting::USES_UTF8 => [
                'default' => true,
            ],
            Setting::BARCODE_TYPE_IS_128 => [
                'default' => true,
            ],
            Setting::FONT_FAMILY => [
                'default' => Setting::DEFAULT_FONT_FAMILY
            ],
            Setting::SHOW_LOGIN_LOGO => [
              'default' => true,
            ],
            Setting::FILE_OVERCONSUMPTION_LOGO => [],
            Setting::FILE_WEBSITE_LOGO => [
                'default' => Setting::DEFAULT_WEBSITE_LOGO_VALUE
            ],
            Setting::FILE_EMAIL_LOGO => [
                'default' => Setting::DEFAULT_EMAIL_LOGO_VALUE
            ],
            Setting::FILE_MOBILE_LOGO_LOGIN => [
                'default' => Setting::DEFAULT_MOBILE_LOGO_LOGIN_VALUE
            ],
            Setting::FILE_MOBILE_LOGO_HEADER => [
                'default' => Setting::DEFAULT_MOBILE_LOGO_HEADER_VALUE
            ],
            Setting::DEFAULT_DELIVERY_WAYBILL_TEMPLATE => [
                'default' => Setting::DEFAULT_DELIVERY_WAYBILL_TEMPLATE_VALUE
            ],
            Setting::DEFAULT_DISPATCH_RECAP_TEMPLATE => [
                'default' => Setting::DEFAULT_DISPATCH_RECAP_TEMPLATE_VALUE
            ],
            Setting::DEFAULT_DISPATCH_SHIPMENT_NOTE => [
                'default' => Setting::DEFAULT_DISPATCH_SHIPMENT_NOTE_VALUE
            ],
            Setting::DEFAULT_DISPATCH_WAYBILL_TEMPLATE => [
                'default' => Setting::DEFAULT_DISPATCH_WAYBILL_TEMPLATE_VALUE
            ],
            Setting::DEFAULT_DISPATCH_WAYBILL_TEMPLATE_WITH_RUPTURE => [
                'default' => Setting::DEFAULT_DISPATCH_WAYBILL_TEMPLATE_VALUE_WITH_RUPTURE
            ],
            Setting::DEFAULT_DELIVERY_SLIP_TEMPLATE => [
                'default' => Setting::DEFAULT_DELIVERY_SLIP_TEMPLATE_VALUE
            ],
            Setting::DEFAULT_PURCHASE_ORDER_TEMPLATE => [
                "default" => Setting::DEFAULT_PURCHASE_ORDER_TEMPLATE_VALUE,
            ],
            Setting::CUSTOM_DELIVERY_WAYBILL_TEMPLATE => [],
            Setting::CUSTOM_DISPATCH_RECAP_TEMPLATE => [],
            Setting::CUSTOM_DISPATCH_SHIPMENT_NOTE => [],
            Setting::CUSTOM_DISPATCH_WAYBILL_TEMPLATE => [],
            Setting::CUSTOM_DISPATCH_WAYBILL_TEMPLATE_WITH_RUPTURE => [],
            Setting::CUSTOM_DELIVERY_SLIP_TEMPLATE => [],
            Setting::CUSTOM_PURCHASE_ORDER_TEMPLATE => [],
            Setting::DEFAULT_LOCATION_RECEPTION => [],
            Setting::DEFAULT_LOCATION_REFERENCE => [],
            Setting::DEFAULT_LOCATION_LIVRAISON => [
                'default' => [],
            ],
            Setting::MVT_DEPOSE_DESTINATION => [],
            Setting::DROP_OFF_LOCATION_IF_CUSTOMS => [],
            Setting::DROP_OFF_LOCATION_IF_EMERGENCY => [],
            Setting::DROP_OFF_LOCATION_IF_RECIPIENT => [],
            Setting::ARRIVAL_EMERGENCY_TRIGGERING_FIELDS => [
                "default" => json_encode(["provider", "commande"]),
            ],
            Setting::CONFIRM_EMERGENCY_ON_ARRIVAL => [
                'default' => false,
            ],
            Setting::LABEL_LOGO => [],
            Setting::EMERGENCY_ICON => [],
            Setting::CUSTOM_ICON => [],
            Setting::CUSTOM_TEXT_LABEL => [],
            Setting::EMERGENCY_TEXT_LABEL => [],
            Setting::DELIVERY_NOTE_LOGO => [],
            Setting::FILE_WAYBILL_LOGO => [], // TODO WIIS-8882
            Setting::DISPATCH_EXPECTED_DATE_COLOR_AFTER => [
                'default' => null,
                SpecificService::CLIENT_TRUFFADE => '#2b78e4'
            ],
            Setting::DISPATCH_EXPECTED_DATE_COLOR_D_DAY => [
                'default' => null,
                SpecificService::CLIENT_TRUFFADE => '#009e0f'
            ],
            Setting::DISPATCH_EXPECTED_DATE_COLOR_BEFORE => [
                'default' => null,
                SpecificService::CLIENT_TRUFFADE => '#cf2a27'
            ],

            Setting::HANDLING_EXPECTED_DATE_COLOR_AFTER => [
                'default' => null,
                SpecificService::CLIENT_TRUFFADE => '#2b78e4'
            ],
            Setting::HANDLING_EXPECTED_DATE_COLOR_D_DAY => [
                'default' => null,
                SpecificService::CLIENT_TRUFFADE => '#009e0f'
            ],
            Setting::HANDLING_EXPECTED_DATE_COLOR_BEFORE => [
                'default' => null,
                SpecificService::CLIENT_TRUFFADE => '#cf2a27'
            ],
            Setting::SEND_PACK_DELIVERY_REMIND => [
                'default' => 0,
                SpecificService::CLIENT_ALIGOT => true
            ],
            Setting::NON_BUSINESS_HOURS_MESSAGE => [
                'default' => null,
            ],
            Setting::SHIPMENT_NOTE_COMPANY_DETAILS => [
                'default' => null,
            ],
            Setting::SHIPMENT_NOTE_SENDER_DETAILS => [
                'default' => null,
            ],
            Setting::SHIPMENT_NOTE_ORIGINATOR => [
                'default' => null,
            ],
            Setting::FILE_SHIPMENT_NOTE_LOGO => [
                'default' => null,
            ],
            Setting::FILE_TOP_LEFT_LOGO => [
                'default' => Setting::DEFAULT_TOP_LEFT_VALUE,
            ],
            Setting::FILE_TOP_RIGHT_LOGO => [
                'default' => null,
            ],
            Setting::FILE_LABEL_EXAMPLE_LOGO => [
                'default' => Setting::DEFAULT_LABEL_EXAMPLE_VALUE,
            ],
            Setting::STATUT_REFERENCE_CREATE => [
                'default' => "actif"
            ],
            Setting::WELCOME_MESSAGE => [
                'default' => 'Veuillez scanner l’étiquette pour faire une entrée de stock.'
            ],
            Setting::INFORMATION_MESSAGE => [
                'default' => 'Si vous rencontrez un problème ou une difficulté, merci de contacter GT au 8 45 65.'
            ],
            Setting::SCAN_ARTICLE_LABEL_MESSAGE => [
                'default' => 'Veuillez scanner l’étiquette déjà présente sur le lot pour renseigner automatiquement l’article à remettre en stock.'
            ],
            Setting::VALIDATION_REFERENCE_ENTRY_MESSAGE => [
                'default' => 'La nouvelle référence @reference a bien été entrée en stock, une étiquette vient d’être imprimée.'
            ],
            Setting::VALIDATION_ARTICLE_ENTRY_MESSAGE => [
                'default' => 'L’article @codearticle issu de la référence @reference a bien été entré en stock.'
            ],
            Setting::QUANTITY_ERROR_MESSAGE => [
                'default' => 'La référence @reference est déjà en stock en quantité 1, vous ne pouvez donc pas faire une nouvelle entrée en stock pour cet article. Contactez GT au 8 45 65 pour plus d’informations.'
            ],
            Setting::DELIVERY_EXPECTED_DATE_COLOR_AFTER => [
                'default' => '#2b78e4'
            ],
            Setting::DELIVERY_EXPECTED_DATE_COLOR_D_DAY => [
                'default' => '#009e0f'
            ],
            Setting::DELIVERY_EXPECTED_DATE_COLOR_BEFORE => [
                'default' => '#cf2a27'
            ],
            Setting::DISPATCH_WAYBILL_TYPE_TO_USE => [
                'default' => Setting::DISPATCH_WAYBILL_TYPE_TO_USE_STANDARD
            ],
            Setting::RFID_PREFIX => [],
            Setting::RFID_KPI_MIN => [],
            Setting::RFID_KPI_MAX => [],
            Setting::BR_ASSOCIATION_DEFAULT_MVT_LOCATION_UL => [
                'default' => null,
            ],
            Setting::BR_ASSOCIATION_DEFAULT_MVT_LOCATION_RECEPTION_NUM => [
                'default' => null,
            ],
            Setting::RECEIVER_EQUALS_REQUESTER => [
                'default' => false,
            ],
            Setting::ALLOWED_DROP_ON_FREE_LOCATION => [
                'default' => false,
            ],
            Setting::DISPLAY_REFERENCE_CODE_AND_SCANNABLE => [
                'default' => false,
            ],
            Setting::DELIVERY_REQUEST_ADD_UL => [
                'default' => false,
            ],
            Setting::DISPATCH_NUMBER_FORMAT => [
                'default' => UniqueNumberService::DATE_COUNTER_FORMAT_DISPATCH_LONG,
            ],
            Setting::DELIVERY_STATION_TOP_LEFT_LOGO => [
                'default' => Setting::DEFAULT_TOP_LEFT_VALUE,
            ],
            Setting::DELIVERY_STATION_TOP_RIGHT_LOGO => [
                'default' => null,
            ],
            Setting::DELIVERY_STATION_INFORMATION_MESSAGE => [
                'default' => 'Si vous rencontrez un problème ou une difficulté, merci de contacter GT au 8 45 65.',
            ],
            Setting::AUTOMATICALLY_CREATE_MOVEMENT_ON_VALIDATION => [
                'default' => false,
            ],
            Setting::AUTOMATICALLY_CREATE_MOVEMENT_ON_VALIDATION_TYPES => [
                'default' => null,
            ],
            Setting::AUTO_UNGROUP => [
                'default' => false,
            ],
            Setting::AUTO_UNGROUP_TYPES => [
                'default' => null,
            ],
            Setting::ARTICLE_LOCATION_DROP_WITH_REFERENCE_STORAGE_RULES => [
                'default' => null,
            ],
            Setting::WARNING_HEADER => [
                "default" => null,
            ],
            Setting::DISPATCH_FIXED_FIEDS_ON_FILTERS => [
                'default' => join(',', [
                    FixedFieldStandard::FIELD_CODE_CARRIER_DISPATCH,
                    FixedFieldStandard::FIELD_CODE_CARRIER_TRACKING_NUMBER_DISPATCH,
                    FixedFieldStandard::FIELD_CODE_RECEIVER_DISPATCH,
                    FixedFieldStandard::FIELD_CODE_DEADLINE_DISPATCH,
                    FixedFieldStandard::FIELD_CODE_EMERGENCY,
                    FixedFieldStandard::FIELD_CODE_COMMAND_NUMBER_DISPATCH,
                    FixedFieldStandard::FIELD_CODE_COMMENT_DISPATCH,
                    FixedFieldStandard::FIELD_CODE_ATTACHMENTS_DISPATCH,
                    FixedFieldStandard::FIELD_CODE_BUSINESS_UNIT,
                    FixedFieldStandard::FIELD_CODE_PROJECT_NUMBER,
                    FixedFieldStandard::FIELD_CODE_LOCATION_PICK,
                    FixedFieldStandard::FIELD_CODE_LOCATION_DROP,
                    FixedFieldStandard::FIELD_CODE_REQUESTER_DISPATCH,
                ]),
            ],
            Setting::PRODUCTION_FIXED_FIELDS_ON_FILTERS => [
                'default' => join(',', [
                    FixedFieldEnum::manufacturingOrderNumber->name,
                    FixedFieldEnum::emergency->name,
                    FixedFieldEnum::expectedAt->name,
                    FixedFieldEnum::projectNumber->name,
                    FixedFieldEnum::productArticleCode->name,
                    FixedFieldEnum::dropLocation->name,
                    FixedFieldEnum::comment->name,
                    FixedFieldEnum::attachments->name,
                    FixedFieldEnum::quantity->name,
                    FixedFieldEnum::lineCount->name,
                    FixedFieldEnum::destinationLocation->name,
                ]),
            ],
            Setting::INCLUDE_TRUCK_ARRIVAL_DATE_AND_HOUR => [
                'default' => false,
            ],
            Setting::INCLUDE_TRUCK_ARRIVAL_DATE_AND_HOUR_BARCODE => [
                'default' => false,
            ],
            Setting::INCLUDE_PACK_NATURE => [
                'default' => false,
            ],
            Setting::AUTO_PRINT_TRUCK_ARRIVAL_LABEL => [
                'default' => false,
            ],
            Setting::DISPLAY_MANUAL_DELAY_START => [
                'default' => 0,
            ],
            Setting::FORMAT_CODE_ARRIVALS => [
                'default' => UniqueNumberService::DATE_COUNTER_FORMAT_ARRIVAL_LONG,
            ]
        ];

        $appClient = $this->specificService->getAppClient();
        foreach ($globalParameterLabels as $globalParameterLabel => $values) {
            $globalParam = $parametreGlobalRepository->findBy(['label' => $globalParameterLabel]);

            $value = $values[$appClient] ?? ($values['default'] ?? null);

            $value = is_array($value)
                ? json_encode($value)
                : $value;
            if (empty($globalParam)) {
                $globalParam = new Setting();
                $globalParam
                    ->setLabel($globalParameterLabel)
                    ->setValue($value);
                $manager->persist($globalParam);
                $output->writeln("Création du paramètre " . $globalParameterLabel);
            }
            else if (in_array($globalParameterLabel, self::FORCE_RESET_CONSTANTS)) {
                $globalParam[0]->setValue($value);
                $output->writeln("Mise à jour du paramètre " . $globalParameterLabel);
            }
        }

        $manager->flush();
    }

    public static function getGroups(): array {
        return ['fixtures'];
    }
}
