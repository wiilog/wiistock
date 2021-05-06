<?php

namespace App\DataFixtures;

use App\Entity\FreeField;
use App\Entity\DimensionsEtiquettes;
use App\Entity\ParametrageGlobal;
use App\Entity\Parametre;

use App\Service\SpecificService;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;

use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Output\ConsoleOutput;

class ParametreFixtures extends Fixture implements FixtureGroupInterface {

    /**
     * @var SpecificService
     */
    private $specificService;

    public function __construct(SpecificService $specificService) {
        $this->specificService = $specificService;
    }

    public function load(ObjectManager $manager) {
        $output = new ConsoleOutput();

        $parameters = [
            [
                'label' => Parametre::LABEL_AJOUT_QUANTITE,
                'type' => Parametre::TYPE_LIST,
                'elements' => [Parametre::VALUE_PAR_ART, Parametre::VALUE_PAR_REF],
                'default' => Parametre::VALUE_PAR_REF
            ]
        ];

        $parametreRepository = $manager->getRepository(Parametre::class);
        $parametreGlobalRepository = $manager->getRepository(ParametrageGlobal::class);
        $dimensionEtiquetteRepository = $manager->getRepository(DimensionsEtiquettes::class);

        foreach ($parameters as $parameter) {
            $param = $parametreRepository->findBy(['label' => $parameter['label']]);

            if (empty($param)) {
                $param = new Parametre();
                $param
                    ->setLabel($parameter['label'])
                    ->setTypage($parameter['type'])
                    ->setDefaultValue($parameter['default'])
                    ->setElements($parameter['elements']);
                $manager->persist($param);
                $output->writeln("Création du paramètre " . $parameter['label']);
            }
        }

        $dimensionEtiquette = $dimensionEtiquetteRepository->findOneDimension();
        $globalParameterLabels = [
            ParametrageGlobal::CREATE_DL_AFTER_RECEPTION => [
                'default' => false,
                SpecificService::CLIENT_COLLINS_SOA => true,
                SpecificService::CLIENT_COLLINS_VERNON => true
            ],
            ParametrageGlobal::CREATE_PREPA_AFTER_DL => [
                'default' => false,
                SpecificService::CLIENT_COLLINS_SOA => true,
                SpecificService::CLIENT_COLLINS_VERNON => true
            ],
            ParametrageGlobal::INCLUDE_BL_IN_LABEL => [
                'default' => false,
                SpecificService::CLIENT_COLLINS_SOA => true,
                SpecificService::CLIENT_COLLINS_VERNON => true
            ],
            ParametrageGlobal::REDIRECT_AFTER_NEW_ARRIVAL => [
                'default' => true,
                SpecificService::CLIENT_SAFRAN_ED => false
            ],
            ParametrageGlobal::SEND_MAIL_AFTER_NEW_ARRIVAL => [
                'default' => false,
                SpecificService::CLIENT_SAFRAN_ED => true
            ],
            ParametrageGlobal::INCLUDE_DZ_LOCATION_IN_LABEL => [
                'default' => true,
            ],
            ParametrageGlobal::INCLUDE_ARRIVAL_TYPE_IN_LABEL => [
                'default' => true,
            ],
            ParametrageGlobal::INCLUDE_EMERGENCY_IN_LABEL => [
                'default' => false,
            ],
            ParametrageGlobal::INCLUDE_CUSTOMS_IN_LABEL => [
                'default' => false,
            ],
            ParametrageGlobal::INCLUDE_PACK_COUNT_IN_LABEL => [
                'default' => true,
            ],
            ParametrageGlobal::INCLUDE_RECIPIENT_IN_LABEL => [
                'default' => false,
            ],
            ParametrageGlobal::INCLUDE_COMMAND_AND_PROJECT_NUMBER_IN_LABEL => [
                'default' => true,
            ],
            ParametrageGlobal::INCLUDE_DESTINATION_LOCATION_IN_ARTICLE_LABEL => [
                'default' => false,
            ],
            ParametrageGlobal::INCLUDE_RECIPIENT_IN_ARTICLE_LABEL => [
                'default' => false,
            ],
            ParametrageGlobal::INCLUDE_RECIPIENT_DROPZONE_LOCATION_IN_ARTICLE_LABEL => [
                'default' => false,
            ],
            ParametrageGlobal::INCLUDE_BATCH_NUMBER_IN_ARTICLE_LABEL => [
                'default' => false,
            ],
            ParametrageGlobal::INCLUDE_EXPIRATION_DATE_IN_ARTICLE_LABEL => [
                'default' => false,
            ],
            ParametrageGlobal::DISPATCH_WAYBILL_CONTACT_PHONE_OR_MAIL => [
                'default' => null,
            ],
            ParametrageGlobal::DISPATCH_OVERCONSUMPTION_BILL_TYPE_AND_STATUS => [
                'default' => null,
            ],
            ParametrageGlobal::DISPATCH_WAYBILL_CONTACT_NAME => [
                'default' => null,
            ],
            ParametrageGlobal::AUTO_PRINT_COLIS => [
                'default' => true,
            ],
            ParametrageGlobal::SEND_MAIL_MANAGER_WARNING_THRESHOLD => [
                'default' => false,
                SpecificService::CLIENT_ARCELOR => true
            ],
            ParametrageGlobal::SEND_MAIL_MANAGER_SECURITY_THRESHOLD => [
                'default' => false,
                SpecificService::CLIENT_ARCELOR => true
            ],
            ParametrageGlobal::STOCK_EXPIRATION_DELAY => [],
            ParametrageGlobal::CL_USED_IN_LABELS => [
                'default' => FreeField::SPECIC_COLLINS_BL
            ],
            ParametrageGlobal::CLOSE_AND_CLEAR_AFTER_NEW_MVT => [
                'default' => true,
                SpecificService::CLIENT_SAFRAN_ED => false
            ],
            ParametrageGlobal::USES_UTF8 => [
                'default' => true,
            ],
            ParametrageGlobal::BARCODE_TYPE_IS_128 => [
                'default' => true,
            ],
            ParametrageGlobal::FONT_FAMILY => [
                'default' => ParametrageGlobal::DEFAULT_FONT_FAMILY
            ],
            ParametrageGlobal::OVERCONSUMPTION_LOGO => [],
            ParametrageGlobal::WEBSITE_LOGO => [
                'default' => ParametrageGlobal::DEFAULT_WEBSITE_LOGO_VALUE
            ],
            ParametrageGlobal::EMAIL_LOGO => [
                'default' => ParametrageGlobal::DEFAULT_EMAIL_LOGO_VALUE
            ],
            ParametrageGlobal::MOBILE_LOGO_LOGIN => [
                'default' => ParametrageGlobal::DEFAULT_MOBILE_LOGO_LOGIN_VALUE
            ],
            ParametrageGlobal::MOBILE_LOGO_HEADER => [
                'default' => ParametrageGlobal::DEFAULT_MOBILE_LOGO_HEADER_VALUE
            ],
            ParametrageGlobal::DEFAULT_LOCATION_RECEPTION => [],
            ParametrageGlobal::DEFAULT_LOCATION_LIVRAISON => [],
            ParametrageGlobal::MVT_DEPOSE_DESTINATION => [],
            ParametrageGlobal::DROP_OFF_LOCATION_IF_CUSTOMS => [],
            ParametrageGlobal::DROP_OFF_LOCATION_IF_EMERGENCY => [],
            ParametrageGlobal::ARRIVAL_EMERGENCY_TRIGGERING_FIELDS => [
                "default" => json_encode(["provider", "commande"]),
            ],
            ParametrageGlobal::LABEL_LOGO => [],
            ParametrageGlobal::EMERGENCY_ICON => [],
            ParametrageGlobal::CUSTOM_ICON => [],
            ParametrageGlobal::CUSTOM_TEXT_LABEL => [],
            ParametrageGlobal::EMERGENCY_TEXT_LABEL => [],
            ParametrageGlobal::DELIVERY_NOTE_LOGO => [],
            ParametrageGlobal::WAYBILL_LOGO => [],
            ParametrageGlobal::KEEP_DISPATCH_PACK_MODAL_OPEN => [
                "default" => false
            ],
            ParametrageGlobal::DISPATCH_EXPECTED_DATE_COLOR_AFTER => [
                'default' => null,
                SpecificService::CLIENT_ARKEMA_SERQUIGNY => '#2b78e4'
            ],
            ParametrageGlobal::DISPATCH_EXPECTED_DATE_COLOR_D_DAY => [
                'default' => null,
                SpecificService::CLIENT_ARKEMA_SERQUIGNY => '#009e0f'
            ],
            ParametrageGlobal::DISPATCH_EXPECTED_DATE_COLOR_BEFORE => [
                'default' => null,
                SpecificService::CLIENT_ARKEMA_SERQUIGNY => '#cf2a27'
            ],

            ParametrageGlobal::HANDLING_EXPECTED_DATE_COLOR_AFTER => [
                'default' => null,
                SpecificService::CLIENT_ARKEMA_SERQUIGNY => '#2b78e4'
            ],
            ParametrageGlobal::HANDLING_EXPECTED_DATE_COLOR_D_DAY => [
                'default' => null,
                SpecificService::CLIENT_ARKEMA_SERQUIGNY => '#009e0f'
            ],
            ParametrageGlobal::HANDLING_EXPECTED_DATE_COLOR_BEFORE => [
                'default' => null,
                SpecificService::CLIENT_ARKEMA_SERQUIGNY => '#cf2a27'
            ]
        ];

        foreach ($globalParameterLabels as $globalParameterLabel => $values) {
            $globalParam = $parametreGlobalRepository->findBy(['label' => $globalParameterLabel]);

            if (empty($globalParam)) {
                $appClient = $this->specificService->getAppClient();
                $value = isset($values[$appClient])
                    ? $values[$appClient]
                    : ($values['default'] ?? null);

                $globalParam = new ParametrageGlobal();
                $globalParam
                    ->setLabel($globalParameterLabel)
                    ->setValue($value);
                $manager->persist($globalParam);
                $output->writeln("Création du paramètre " . $globalParameterLabel);
            }
        }

        if (!$dimensionEtiquette) {
            $dimensionEtiquette = new DimensionsEtiquettes();
            $dimensionEtiquette
                ->setHeight(ParametrageGlobal::LABEL_HEIGHT_DEFAULT)
                ->setWidth(parametrageGlobal::LABEL_WIDTH_DEFAULT);
            $manager->persist($dimensionEtiquette);
            $output->writeln('Création des dimensions étiquettes');
        }

        $manager->flush();
    }

    public static function getGroups(): array {
        return ['param', 'fixtures'];
    }
}
