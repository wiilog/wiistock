<?php


namespace App\Service;

use App\Entity\ParametrageGlobal;
use App\Repository\DimensionsEtiquettesRepository;
use App\Repository\EmplacementRepository;
use App\Repository\NatureRepository;
use App\Repository\ParametrageGlobalRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

Class GlobalParamService
{
	private $parametrageGlobalRepository;
	private $dimensionsEtiquettesRepository;
	private $emplacementRepository;
	private $natureRepository;

	public function __construct(
		ParametrageGlobalRepository $parametrageGlobalRepository,
		DimensionsEtiquettesRepository $dimensionsEtiquettesRepository,
		EmplacementRepository $emplacementRepository,
		NatureRepository $natureRepository
	)
	{
		$this->parametrageGlobalRepository = $parametrageGlobalRepository;
		$this->dimensionsEtiquettesRepository = $dimensionsEtiquettesRepository;
		$this->emplacementRepository = $emplacementRepository;
		$this->natureRepository = $natureRepository;
	}

	/**
	 * @param bool $includeNullDimensions
	 * @return array
	 * @throws NonUniqueResultException
	 * @throws NoResultException
	 */
	public function getDimensionAndTypeBarcodeArray(bool $includeNullDimensions = true) {
		$dimension = $this->dimensionsEtiquettesRepository->findOneDimension();
		$response = [];
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
		$typeBarcode = $this->parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::BARCODE_TYPE_IS_128);
		$response['isCode128'] = $typeBarcode === '1';
		return $response;
	}

	/**
	 * @return array|null
	 * @throws NoResultException
	 * @throws NonUniqueResultException
	 */
	public function getReceptionDefaultLocation() {
		$locationId = $this->parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DEFAULT_LOCATION_RECEPTION);

		if ($locationId) {
			$location = $this->emplacementRepository->find($locationId);

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
	 * @return array
	 * @throws NoResultException
	 * @throws NonUniqueResultException
	 */
	public function getDashboardLocations() {

		$paramLabels = [
			ParametrageGlobal::DASHBOARD_LOCATION_DOCK,
			ParametrageGlobal::DASHBOARD_LOCATION_WAITING_CLEARANCE,
			ParametrageGlobal::DASHBOARD_LOCATION_AVAILABLE,
			ParametrageGlobal::DASHBOARD_LOCATION_TO_DROP_ZONES,
			ParametrageGlobal::DASHBOARD_LOCATION_LITIGES,
			ParametrageGlobal::DASHBOARD_LOCATION_URGENCES,
			ParametrageGlobal::DASHBOARD_LOCATIONS_1,
			ParametrageGlobal::DASHBOARD_LOCATIONS_2
		];



		$resp = [];
		foreach ($paramLabels as $paramLabel) {
			$locationIds = $this->parametrageGlobalRepository->getOneParamByLabel($paramLabel);

			if ($locationIds) {
				$locationIdsArr = explode(',', $locationIds);

				$text = [];
				foreach ($locationIdsArr as $locationId) {
					$location = $this->emplacementRepository->find($locationId);
					$text[] = $location ? $location->getLabel() : '';
				}

				$resp[] = [
					'id' => $locationIds,
					'text' => implode(',', $text)
				];
			} else {
				$resp[] = ['id' => '', 'text' => ''];
			}
		}

		return $resp;
	}

	/**
	 * @return array
	 * @throws NoResultException
	 * @throws NonUniqueResultException
	 */
	public function getDashboardListNatures() {
		$listNatureId = $this->parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DASHBOARD_LIST_NATURES_COLIS);

		$listNatureIdArray = explode(',', $listNatureId);
		$resp = [];

		foreach ($listNatureIdArray as $natureId) {
			$nature = $this->natureRepository->find($natureId);

			if ($nature) {
				$resp[] = $natureId;
			}
		}

		return $resp;
	}

}
