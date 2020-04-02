<?php

namespace App\Service;

use App\Entity\AlerteExpiry;

use App\Entity\ReferenceArticle;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;

class AlerteService
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
		$this->entityManager = $entityManager;
    }

    /**
     * @param AlerteExpiry $alerte
     * @return bool
     * @throws NonUniqueResultException
     */
    public function isAlerteExpiryActive($alerte)
	{
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);

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
			$countRef = $referenceArticleRepository->countWithExpiryDateUpTo($nbPeriod, $typePeriod);

			return $countRef > 0;
		}
	}

}
