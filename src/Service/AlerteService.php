<?php

namespace App\Service;

use App\Entity\AlerteExpiry;

use App\Repository\AlerteExpiryRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\StatutRepository;

class AlerteService
{
	/**
	 * @var AlerteExpiryRepository
	 */
    private $alerteExpiryRepository;

	/**
	 * @var StatutRepository
	 */
    private $statutRepository;

	/**
	 * @var ReferenceArticleRepository
	 */
    private $referenceArticleRepository;


    public function __construct(ReferenceArticleRepository $referenceArticleRepository, StatutRepository $statutRepository, AlerteExpiryRepository $alerteExpiryRepository)
    {
		$this->alerteExpiryRepository = $alerteExpiryRepository;
		$this->statutRepository = $statutRepository;
		$this->referenceArticleRepository = $referenceArticleRepository;
    }

	/**
	 * @param AlerteExpiry $alerte
	 * @return bool
	 * @throws \Exception
	 */
    public function isAlerteExpiryActive($alerte)
	{
		$refArticle = $alerte->getRefArticle();

		if ($refArticle) {
			$expiryDate = $alerte->getRefArticle()->getExpiryDate();
			if (!$expiryDate) return false;

			switch ($alerte->getTypePeriod()) {
				case AlerteExpiry::TYPE_PERIOD_DAY:
					$interval = 'D';
					break;
				case AlerteExpiry::TYPE_PERIOD_WEEK:
					$interval = 'W';
					break;
				case AlerteExpiry::TYPE_PERIOD_MONTH:
					$interval = 'M';
					break;
				default:
					return false;
			}

			$dateAlerte = $expiryDate->sub(new \DateInterval('P' . $alerte->getNbPeriod() . $interval));
			$now = new \DateTime('now');

			return $now >= $dateAlerte;
		} else {
			$nbPeriod = $alerte->getNbPeriod();
			$typePeriod = $alerte->getTypePeriod();
			$countRef = $this->referenceArticleRepository->countWithExpiryDateUpTo($nbPeriod, $typePeriod);

			return $countRef > 0;
		}
	}

}
