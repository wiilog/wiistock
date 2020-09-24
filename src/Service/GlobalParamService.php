<?php


namespace App\Service;

use App\Entity\CategoryType;
use App\Entity\DimensionsEtiquettes;
use App\Entity\Emplacement;
use App\Entity\Nature;
use App\Entity\ParametrageGlobal;
use App\Entity\Transporteur;
use App\Entity\Type;
use App\Repository\CategoryTypeRepository;
use App\Repository\EmplacementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;

Class GlobalParamService
{
	private $kernel;
	private $translationService;
    private $em;

    public function __construct(EntityManagerInterface $em,
                                KernelInterface $kernel,
                                TranslationService $translationService) {
        $this->kernel = $kernel;
        $this->translationService = $translationService;
        $this->em = $em;
    }

	/**
	 * @param bool $includeNullDimensions
	 * @return array
	 * @throws NonUniqueResultException
	 */
	public function getDimensionAndTypeBarcodeArray(bool $includeNullDimensions = true) {
        $dimensionsEtiquettesRepository = $this->em->getRepository(DimensionsEtiquettes::class);
        $parametrageGlobalRepository = $this->em->getRepository(ParametrageGlobal::class);

		$dimension = $dimensionsEtiquettesRepository->findOneDimension();
		$response = [];
		$response['logo'] = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::LABEL_LOGO);
		if ($dimension && !empty($dimension->getHeight()) && !empty($dimension->getWidth()))
		{
			$response['height'] = $dimension->getHeight();
			$response['width'] = $dimension->getWidth();
			$response['exists'] = true;
		} else {
			if($includeNullDimensions) {
				$response['height'] = 0;
				$response['width'] = 0;
			}
			$response['exists'] = false;
		}
		$typeBarcode = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::BARCODE_TYPE_IS_128);
		$response['isCode128'] = $typeBarcode === '1';
		return $response;
	}

	public function treatTypeCreationOrEdition(Type $type, CategoryTypeRepository $categoryTypeRepository, EmplacementRepository $emplacementRepository, array $data) {
        $category = $categoryTypeRepository->find($data['category']);

        $isDispatch = ($category->getLabel() === CategoryType::DEMANDE_DISPATCH);

        $type
            ->setLabel($data['label'])
            ->setSendMail($data["sendMail"] ?? false)
            ->setCategory($category)
            ->setDescription($data['description']);

        if ($isDispatch) {
            $dropLocation = $data["depose"] ? $emplacementRepository->find($data["depose"]) : null;
            $pickLocation = $data["prise"] ? $emplacementRepository->find($data["prise"]) : null;

            $type
                ->setDropLocation($dropLocation)
                ->setPickLocation($pickLocation);
        }
    }

	/**
	 * @return array|null
	 * @throws NonUniqueResultException
	 */
	public function getReceptionDefaultLocation() {
	    $parametrageGlobalRepository = $this->em->getRepository(ParametrageGlobal::class);
	    $emplacementRepository = $this->em->getRepository(Emplacement::class);

		$locationId = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DEFAULT_LOCATION_RECEPTION);

		if ($locationId) {
			$location = $emplacementRepository->find($locationId);

			if ($location) {
				$resp = [
					'id' => $locationId,
					'text' => $location->getLabel()
				];
			}
		}
		return $resp ?? null;
	}

	/**
	 * @return array|null
	 * @throws NonUniqueResultException
	 */
	public function getMvtDeposeArrival() {
	    $parametrageGlobalRepository = $this->em->getRepository(ParametrageGlobal::class);
	    $emplacementRepository = $this->em->getRepository(Emplacement::class);

		$locationId = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::MVT_DEPOSE_DESTINATION);

		if ($locationId) {
			$location = $emplacementRepository->find($locationId);

			if ($location) {
				$resp = [
					'id' => $locationId,
					'text' => $location->getLabel()
				];
			}
		}
		return $resp ?? null;
	}

	public function getLivraisonDefaultLocation() {
        $parametrageGlobalRepository = $this->em->getRepository(ParametrageGlobal::class);
        $emplacementRepository = $this->em->getRepository(Emplacement::class);

        $locationId = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DEFAULT_LOCATION_LIVRAISON);

        if ($locationId) {
            $location = $emplacementRepository->find($locationId);

            if ($location) {
                $resp = [
                    'id' => $locationId,
                    'text' => $location->getLabel()
                ];
            }
        }
        return $resp ?? null;
    }

    public function getDashboardCarrierDock()
    {
        $parametrageGlobalRepository = $this->em->getRepository(ParametrageGlobal::class);
        $transporteurRepository = $this->em->getRepository(Transporteur::class);

        $carriersId = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DASHBOARD_CARRIER_DOCK);

        if (!empty($carriersId)) {
            $ids = $texts = [];

            foreach (explode(',', $carriersId) as $id) {
                $transporteur = $transporteurRepository->find($id);

                if ($transporteur) {
                    $ids[] = $id;
                    $texts[] = $transporteur->getLabel();
                }
            }

            $resp = [
                'id' => implode(',', $ids),
                'text' => implode(',', $texts)
            ];
        }

        return $resp ?? [];
    }

	/**
	 * @throws NonUniqueResultException
	 */
	public function generateScssFile()
    {
        $parametrageGlobalRepository = $this->em->getRepository(ParametrageGlobal::class);

        $projectDir = $this->kernel->getProjectDir();
		$scssFile = $projectDir . '/assets/scss/_customFont.scss';

		$param = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::FONT_FAMILY);
		$font = $param ? $param->getValue() : ParametrageGlobal::DEFAULT_FONT_FAMILY;

		$scssText = '$mainFont: "' . $font . '";';
		file_put_contents($scssFile, $scssText);

		$this->compileScss();
	}

	/**
	 * @throws Exception
	 */
	private function compileScss() {
		$env = $this->kernel->getEnvironment();

		$command = $env == 'dev' ? 'dev' : 'production';
		$process = Process::fromShellCommandline('yarn build:only:' . $command);
		$process->run();
	}

    /**
     * @return array
     * @throws NonUniqueResultException
     */
	public function getDashboardLocations()
    {
        $parametrageGlobalRepository = $this->em->getRepository(ParametrageGlobal::class);
        $emplacementRepository = $this->em->getRepository(Emplacement::class);

		$paramLabels = [
			ParametrageGlobal::DASHBOARD_LOCATION_DOCK,
			ParametrageGlobal::DASHBOARD_LOCATION_WAITING_CLEARANCE_DOCK,
			ParametrageGlobal::DASHBOARD_LOCATION_WAITING_CLEARANCE_ADMIN,
			ParametrageGlobal::DASHBOARD_LOCATION_AVAILABLE,
			ParametrageGlobal::DASHBOARD_LOCATION_TO_DROP_ZONES,
			ParametrageGlobal::DASHBOARD_LOCATION_LITIGES,
			ParametrageGlobal::DASHBOARD_LOCATION_URGENCES,
			ParametrageGlobal::DASHBOARD_LOCATIONS_1,
            ParametrageGlobal::DASHBOARD_LOCATIONS_2,
            ParametrageGlobal::DASHBOARD_PACKAGING_1,
            ParametrageGlobal::DASHBOARD_PACKAGING_2,
            ParametrageGlobal::DASHBOARD_PACKAGING_3,
            ParametrageGlobal::DASHBOARD_PACKAGING_4,
            ParametrageGlobal::DASHBOARD_PACKAGING_5,
            ParametrageGlobal::DASHBOARD_PACKAGING_6,
            ParametrageGlobal::DASHBOARD_PACKAGING_7,
            ParametrageGlobal::DASHBOARD_PACKAGING_8,
            ParametrageGlobal::DASHBOARD_PACKAGING_RPA,
            ParametrageGlobal::DASHBOARD_PACKAGING_LITIGE,
            ParametrageGlobal::DASHBOARD_PACKAGING_URGENCE,
            ParametrageGlobal::DASHBOARD_PACKAGING_DSQR,
            ParametrageGlobal::DASHBOARD_PACKAGING_DESTINATION_GT,
            ParametrageGlobal::DASHBOARD_PACKAGING_ORIGINE_GT,
		];

		$resp = [];
		foreach ($paramLabels as $paramLabel) {
			$locationIds = $parametrageGlobalRepository->getOneParamByLabel($paramLabel);

			if ($locationIds) {
				$locationIdsArr = explode(',', $locationIds);

				$text = [];
				foreach ($locationIdsArr as $locationId) {
					$location = $emplacementRepository->find($locationId);
					$text[] = $location ? $location->getLabel() : '';
				}

				$resp[$paramLabel] = [
					'id' => $locationIds,
					'text' => implode(',', $text)
				];
			} else {
				$resp[$paramLabel] = ['id' => '', 'text' => ''];
			}
		}

		return $resp;
	}

	/**
	 * @return array
     * @throws NonUniqueResultException
	 */
	public function getDashboardListNatures() {
        $parametrageGlobalRepository = $this->em->getRepository(ParametrageGlobal::class);
        $natureRepository = $this->em->getRepository(Nature::class);

        $listNatureId = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DASHBOARD_LIST_NATURES_COLIS);

		$listNatureIdArray = explode(',', $listNatureId);
		$resp = [];

		foreach ($listNatureIdArray as $natureId) {
			$nature = $natureRepository->find($natureId);

			if ($nature) {
				$resp[] = $natureId;
			}
		}

		return $resp;
	}

}
