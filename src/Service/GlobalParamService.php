<?php


namespace App\Service;

use App\Entity\ParametrageGlobal;
use App\Repository\DimensionsEtiquettesRepository;
use App\Repository\ParametrageGlobalRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

Class GlobalParamService
{
	private $parametrageGlobalRepository;
	private $dimensionsEtiquettesRepository;

	public function __construct(
		ParametrageGlobalRepository $parametrageGlobalRepository,
		DimensionsEtiquettesRepository $dimensionsEtiquettesRepository
	)
	{
		$this->parametrageGlobalRepository = $parametrageGlobalRepository;
		$this->dimensionsEtiquettesRepository = $dimensionsEtiquettesRepository;
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

}
