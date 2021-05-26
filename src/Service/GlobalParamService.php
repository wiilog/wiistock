<?php


namespace App\Service;

use App\Entity\CategoryType;
use App\Entity\DimensionsEtiquettes;
use App\Entity\Emplacement;
use App\Entity\ParametrageGlobal;
use App\Entity\Type;
use App\Repository\CategoryTypeRepository;
use App\Repository\EmplacementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

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

	public function getParamLocation(string $label) {
        $parametrageGlobalRepository = $this->em->getRepository(ParametrageGlobal::class);
        $emplacementRepository = $this->em->getRepository(Emplacement::class);

        $locationId = $parametrageGlobalRepository->getOneParamByLabel($label);

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

    public function generateScssFile(?ParametrageGlobal $font = null) {
        $projectDir = $this->kernel->getProjectDir();
        $scssFile = $projectDir . '/assets/scss/_customFont.scss';

        if(!$font) {
            $parametrageGlobalRepository = $this->em->getRepository(ParametrageGlobal::class);
            $param = $parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::FONT_FAMILY);
            $font = $param ? $param->getValue() : ParametrageGlobal::DEFAULT_FONT_FAMILY;
        } else {
            $font = $font->getValue();
        }

		file_put_contents($scssFile, "\$mainFont: \"$font\";");
	}

	public function getDefaultDeliveryLocationsByType(EntityManagerInterface $entityManager): array {

        $typeRepository = $entityManager->getRepository(Type::class);
        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);

        $defaultDeliveryLocationsParam = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DEFAULT_LOCATION_LIVRAISON);
        $defaultDeliveryLocationsIds = json_decode($defaultDeliveryLocationsParam, true);

        $defaultDeliveryLocations = [];
        foreach ($defaultDeliveryLocationsIds as $typeId => $locationId) {
            if ($typeId !== 'all' && $typeId) {
                $type = $typeRepository->find($typeId);
            }
            if ($locationId) {
                $location = $locationRepository->find($locationId);
            }

            $defaultDeliveryLocations[] = [
                'location' => isset($location)
                    ? [
                        'label' => $location->getLabel(),
                        'id' => $location->getId()
                    ]
                    : null,
                'type' => isset($type)
                    ? [
                        'label' => $type->getLabel(),
                        'id' => $type->getId()
                    ]
                    : null,
            ];
        }
        return $defaultDeliveryLocations;
    }

	public function getDefaultDeliveryLocationsByTypeId(EntityManagerInterface $entityManager): array {

        $locationRepository = $entityManager->getRepository(Emplacement::class);
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);

        $defaultDeliveryLocationsParam = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DEFAULT_LOCATION_LIVRAISON);
        $defaultDeliveryLocationsIds = json_decode($defaultDeliveryLocationsParam, true);

        $defaultDeliveryLocations = [];
        foreach ($defaultDeliveryLocationsIds as $typeId => $locationId) {
            if ($locationId) {
                $location = $locationRepository->find($locationId);
            }

            $defaultDeliveryLocations[$typeId] = isset($location)
                ? [
                    'label' => $location->getLabel(),
                    'id' => $location->getId()
                ]
                : null;
        }
        return $defaultDeliveryLocations;
    }

}
