<?php

namespace App\DataFixtures;

use App\Entity\FreeField;
use App\Entity\Setting;

use App\Service\SpecificService;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;

use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;

class SettingFixtures extends Fixture implements FixtureGroupInterface {

    /** @Required */
    public SpecificService $specificService;

    public function load(ObjectManager $manager) {
        $output = new ConsoleOutput();

        $parametreGlobalRepository = $manager->getRepository(Setting::class);

        $globalParameterLabels = [
            Setting::MAX_SESSION_TIME => [
                'default' => 30,
            ],
            Setting::CREATE_DL_AFTER_RECEPTION => [
                'default' => false,
                SpecificService::CLIENT_COLLINS_SOA => true,
                SpecificService::CLIENT_COLLINS_VERNON => true
            ],
            Setting::CREATE_PREPA_AFTER_DL => [
                'default' => true,
            ],
            Setting::CREATE_DELIVERY_ONLY => [
                'default' => false,
            ],
            Setting::DIRECT_DELIVERY => [
                'default' => false,
                SpecificService::CLIENT_ARCELOR => true
            ],
            Setting::MANAGE_LOCATION_DELIVERY_DROPDOWN_LIST => [
                'default' => false,
                SpecificService::CLIENT_SAFRAN_ED => true,
            ],
            Setting::MANAGE_PREPARATIONS_WITH_PLANNING => [
                'default' => false,
            ],
            Setting::MANAGE_LOCATION_COLLECTE_DROPDOWN_LIST => [
                'default' => false,
                SpecificService::CLIENT_SAFRAN_ED => true,
            ],
            Setting::INCLUDE_BL_IN_LABEL => [
                'default' => false,
                SpecificService::CLIENT_COLLINS_SOA => true,
                SpecificService::CLIENT_COLLINS_VERNON => true
            ],
            Setting::REDIRECT_AFTER_NEW_ARRIVAL => [
                'default' => true,
                SpecificService::CLIENT_SAFRAN_ED => false
            ],
            Setting::SEND_MAIL_AFTER_NEW_ARRIVAL => [
                'default' => false,
                SpecificService::CLIENT_SAFRAN_ED => true
            ],
            Setting::INCLUDE_DZ_LOCATION_IN_LABEL => [
                'default' => true,
            ],
            Setting::INCLUDE_ARRIVAL_TYPE_IN_LABEL => [
                'default' => true,
            ],
            setting::INCLUDE_BUSINESS_UNIT_IN_LABEL => [
                'default' => false,
                SpecificService::CLIENT_INEO_LAV => true,
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
            Setting::DISPATCH_OVERCONSUMPTION_BILL_TYPE_AND_STATUS => [
                'default' => null,
            ],
            Setting::DISPATCH_WAYBILL_CONTACT_NAME => [
                'default' => null,
            ],
            Setting::AUTO_PRINT_COLIS => [
                'default' => true,
            ],
            Setting::SEND_MAIL_MANAGER_WARNING_THRESHOLD => [
                'default' => false,
                SpecificService::CLIENT_ARCELOR => true
            ],
            Setting::SEND_MAIL_MANAGER_SECURITY_THRESHOLD => [
                'default' => false,
                SpecificService::CLIENT_ARCELOR => true
            ],
            Setting::STOCK_EXPIRATION_DELAY => [],
            Setting::CL_USED_IN_LABELS => [
                'default' => FreeField::SPECIC_COLLINS_BL
            ],
            Setting::CLOSE_AND_CLEAR_AFTER_NEW_MVT => [
                'default' => true,
                SpecificService::CLIENT_SAFRAN_ED => false
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
            Setting::DEFAULT_LOCATION_RECEPTION => [],
            Setting::DEFAULT_LOCATION_REFERENCE => [],
            Setting::DEFAULT_LOCATION_LIVRAISON => [
                'default' => [],
            ],
            Setting::MVT_DEPOSE_DESTINATION => [],
            Setting::DROP_OFF_LOCATION_IF_CUSTOMS => [],
            Setting::DROP_OFF_LOCATION_IF_EMERGENCY => [],
            Setting::ARRIVAL_EMERGENCY_TRIGGERING_FIELDS => [
                "default" => json_encode(["provider", "commande"]),
            ],
            Setting::LABEL_LOGO => [],
            Setting::EMERGENCY_ICON => [],
            Setting::CUSTOM_ICON => [],
            Setting::CUSTOM_TEXT_LABEL => [],
            Setting::EMERGENCY_TEXT_LABEL => [],
            Setting::DELIVERY_NOTE_LOGO => [],
            Setting::FILE_WAYBILL_LOGO => [],
            Setting::DISPATCH_EXPECTED_DATE_COLOR_AFTER => [
                'default' => null,
                SpecificService::CLIENT_ARKEMA_SERQUIGNY => '#2b78e4'
            ],
            Setting::DISPATCH_EXPECTED_DATE_COLOR_D_DAY => [
                'default' => null,
                SpecificService::CLIENT_ARKEMA_SERQUIGNY => '#009e0f'
            ],
            Setting::DISPATCH_EXPECTED_DATE_COLOR_BEFORE => [
                'default' => null,
                SpecificService::CLIENT_ARKEMA_SERQUIGNY => '#cf2a27'
            ],

            Setting::HANDLING_EXPECTED_DATE_COLOR_AFTER => [
                'default' => null,
                SpecificService::CLIENT_ARKEMA_SERQUIGNY => '#2b78e4'
            ],
            Setting::HANDLING_EXPECTED_DATE_COLOR_D_DAY => [
                'default' => null,
                SpecificService::CLIENT_ARKEMA_SERQUIGNY => '#009e0f'
            ],
            Setting::HANDLING_EXPECTED_DATE_COLOR_BEFORE => [
                'default' => null,
                SpecificService::CLIENT_ARKEMA_SERQUIGNY => '#cf2a27'
            ],
            Setting::SEND_PACK_DELIVERY_REMIND => [
                'default' => 0,
                SpecificService::CLIENT_INEO_LAV => true
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
            Setting::COLLECT_REQUEST_DESTINATION => [
                'default' => 'stock'
            ]
        ];

        foreach ($globalParameterLabels as $globalParameterLabel => $values) {
            $globalParam = $parametreGlobalRepository->findBy(['label' => $globalParameterLabel]);

            if (empty($globalParam)) {
                $appClient = $this->specificService->getAppClient();
                $value = $values[$appClient] ?? ($values['default'] ?? null);

                $value = is_array($value)
                    ? json_encode($value)
                    : $value;

                $globalParam = new Setting();
                $globalParam
                    ->setLabel($globalParameterLabel)
                    ->setValue($value);
                $manager->persist($globalParam);
                $output->writeln("Création du paramètre " . $globalParameterLabel);
            }
        }

        $manager->flush();
    }

    public static function getGroups(): array {
        return ['fixtures'];
    }
}
