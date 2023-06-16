<?php

namespace App\Repository;

use App\Entity\Translation;
use App\Service\MobileApiService;
use Doctrine\ORM\EntityRepository;

/**
 * @method Translation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Translation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Translation[]    findAll()
 * @method Translation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TranslationRepository extends EntityRepository {
    public function findForMobile() {
        return $this->createQueryBuilder('translation')
            ->join('translation.source', 'source')
            ->join('source.category', 'category')
            ->andWhere('category.label IN (:mobileLabels)')
            ->setParameter('mobileLabels', MobileApiService::MOBILE_TRANSLATIONS)
            ->getQuery()
            ->getResult();
    }
}
