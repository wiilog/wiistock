<?php

namespace App\Service;

use App\Entity\AlerteExpiry;

use App\Repository\AlerteExpiryRepository;
use App\Repository\EmplacementRepository;

use Symfony\Component\Routing\RouterInterface;
use Doctrine\ORM\EntityManagerInterface;

class AlerteService
{
	/**
	 * @var AlerteExpiryRepository
	 */
    private $alerteExpiryRepository;

    public function __construct(AlerteExpiryRepository $alerteExpiryRepository)
    {
		$this->alerteExpiryRepository = $alerteExpiryRepository;
    }

	/**
	 * @param AlerteExpiry $alerte
	 * @return bool
	 */
    public function isAlerteExpiryReached($alerte)
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
			//TODO CG cas alerte sur toutes réf
		}
	}

}
