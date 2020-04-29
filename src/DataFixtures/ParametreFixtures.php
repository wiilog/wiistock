<?php

namespace App\DataFixtures;

use App\Entity\ChampLibre;
use App\Entity\Arrivage;
use App\Entity\CategorieStatut;
use App\Entity\ParametrageGlobal;
use App\Entity\Parametre;

use App\Entity\Statut;
use App\Repository\ParametrageGlobalRepository;

use App\Service\SpecificService;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;

use Doctrine\Persistence\ObjectManager;

class ParametreFixtures extends Fixture implements FixtureGroupInterface
{

	/**
	 * @var ParametrageGlobalRepository
	 */
	private $parametreGlobalRepository;

	/**
	 * @var SpecificService
	 */
	private $specificService;

    public function __construct(ParametrageGlobalRepository $parametrageGlobalRepository,
                                SpecificService $specificService)
    {
    	$this->parametreGlobalRepository = $parametrageGlobalRepository;
    	$this->specificService = $specificService;
    }

    public function load(ObjectManager $manager)
    {
		$parameters = [
			[
				'label' => Parametre::LABEL_AJOUT_QUANTITE,
				'type' => Parametre::TYPE_LIST,
				'elements' => [Parametre::VALUE_PAR_ART, Parametre::VALUE_PAR_REF],
				'default' => Parametre::VALUE_PAR_REF
			]
		];

        $parametreRepository = $manager->getRepository(Parametre::class);

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
				dump("création du paramètre " . $parameter['label']);
			}
		}
        $statutRepository = $manager->getRepository(Statut::class);
        $statutConformeArrival = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ARRIVAGE, Arrivage::STATUS_CONFORME);
        $statutConformeArrivalId = isset ($statutConformeArrival) ? $statutConformeArrival->getId() : null;

		$globalParameterLabels = [
			ParametrageGlobal::CREATE_DL_AFTER_RECEPTION => [
			    'default' => false,
                SpecificService::CLIENT_COLLINS => true
            ],
			ParametrageGlobal::CREATE_PREPA_AFTER_DL => [
                'default' => false,
                SpecificService::CLIENT_COLLINS => true
            ],
			ParametrageGlobal::INCLUDE_BL_IN_LABEL => [
                'default' => false,
                SpecificService::CLIENT_COLLINS => true
            ],
			ParametrageGlobal::REDIRECT_AFTER_NEW_ARRIVAL => [
                'default' => true,
                SpecificService::CLIENT_SAFRAN_ED => false
            ],
            ParametrageGlobal::SEND_MAIL_AFTER_NEW_ARRIVAL => [
                'default' => false,
                SpecificService::CLIENT_SAFRAN_ED => true
            ],
            ParametrageGlobal::DEFAULT_STATUT_ARRIVAGE => [
                'default' => null,
                SpecificService::CLIENT_SAFRAN_ED => $statutConformeArrivalId
            ],
            ParametrageGlobal::AUTO_PRINT_COLIS => [
                'default' => true,
            ],
            ParametrageGlobal::CL_USED_IN_LABELS => [
                'default' => ChampLibre::SPECIC_COLLINS_BL
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
			ParametrageGlobal::DEFAULT_LOCATION_RECEPTION => [],
			ParametrageGlobal::DEFAULT_STATUT_LITIGE_REC => [],
			ParametrageGlobal::DEFAULT_STATUT_LITIGE_ARR => [],
            ParametrageGlobal::DEFAULT_LOCATION_LIVRAISON => [],
			ParametrageGlobal::DASHBOARD_NATURE_COLIS => [],
			ParametrageGlobal::DASHBOARD_LIST_NATURES_COLIS => [],
			ParametrageGlobal::DASHBOARD_LOCATION_DOCK => [],
			ParametrageGlobal::DASHBOARD_LOCATION_WAITING_CLEARANCE_DOCK => [],
			ParametrageGlobal::DASHBOARD_LOCATION_WAITING_CLEARANCE_ADMIN => [],
			ParametrageGlobal::DASHBOARD_LOCATION_AVAILABLE => [],
			ParametrageGlobal::DASHBOARD_LOCATION_TO_DROP_ZONES => [],
			ParametrageGlobal::DASHBOARD_LOCATIONS_1 => [],
			ParametrageGlobal::DASHBOARD_LOCATIONS_2 => [],
			ParametrageGlobal::DASHBOARD_LOCATION_LITIGES => [],
			ParametrageGlobal::DASHBOARD_LOCATION_URGENCES => [],
            ParametrageGlobal::DASHBOARD_CARRIER_DOCK => [],
            ParametrageGlobal::MVT_DEPOSE_DESTINATION => [],
            ParametrageGlobal::FILE_FOR_LOGO => [],
            ParametrageGlobal::DASHBOARD_PACKAGING_1 => [],
            ParametrageGlobal::DASHBOARD_PACKAGING_2 => [],
            ParametrageGlobal::DASHBOARD_PACKAGING_3 => [],
            ParametrageGlobal::DASHBOARD_PACKAGING_4 => [],
            ParametrageGlobal::DASHBOARD_PACKAGING_5 => [],
            ParametrageGlobal::DASHBOARD_PACKAGING_6 => [],
            ParametrageGlobal::DASHBOARD_PACKAGING_7 => [],
            ParametrageGlobal::DASHBOARD_PACKAGING_8 => [],
            ParametrageGlobal::DASHBOARD_PACKAGING_9 => [],
            ParametrageGlobal::DASHBOARD_PACKAGING_10 => [],
            ParametrageGlobal::DASHBOARD_PACKAGING_11 => [],
            ParametrageGlobal::DASHBOARD_PACKAGING_12 => [],
            ParametrageGlobal::DASHBOARD_PACKAGING_13 => [],
            ParametrageGlobal::DASHBOARD_PACKAGING_14 => [],
        ];

		foreach ($globalParameterLabels as $globalParameterLabel => $values) {
			$globalParam = $this->parametreGlobalRepository->findBy(['label' => $globalParameterLabel]);

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
				dump("création du paramètre " . $globalParameterLabel);
			}
		}

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['param', 'fixtures'];
    }
}
