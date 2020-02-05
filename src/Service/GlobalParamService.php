<?php


namespace App\Service;

use App\Entity\ParametrageGlobal;
use App\Repository\DimensionsEtiquettesRepository;
use App\Repository\EmplacementRepository;
use App\Repository\ParametrageGlobalRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Exception;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;

Class GlobalParamService
{
	private $parametrageGlobalRepository;
	private $dimensionsEtiquettesRepository;
	private $emplacementRepository;
	private $kernel;
	private $translationService;

	public function __construct(
		ParametrageGlobalRepository $parametrageGlobalRepository,
		DimensionsEtiquettesRepository $dimensionsEtiquettesRepository,
		EmplacementRepository $emplacementRepository,
		KernelInterface $kernel,
		TranslationService $translationService
	)
	{
		$this->parametrageGlobalRepository = $parametrageGlobalRepository;
		$this->dimensionsEtiquettesRepository = $dimensionsEtiquettesRepository;
		$this->emplacementRepository = $emplacementRepository;
		$this->kernel = $kernel;
		$this->translationService = $translationService;
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
	 * @throws NonUniqueResultException
	 */
	public function generateSassFile() {
		$projectDir = $this->kernel->getProjectDir();
		$sassFile = $projectDir . '/assets/sass/_customFont.sass';

		$param = $this->parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::FONT_FAMILY);
		$font = $param ? $param->getValue() : ParametrageGlobal::DEFAULT_FONT_FAMILY;

		$sassText = '$mainFont: "' . $font . '"';
		file_put_contents($sassFile, $sassText);

		$this->compileSass();
	}


	/**
	 * @throws Exception
	 */
	private function compileSass() {
		$env = $this->kernel->getEnvironment();

		$command = $env == 'dev' ? 'dev' : 'build';
		$process = Process::fromShellCommandline('yarn ' . $command);
		$process->run();
	}
}
