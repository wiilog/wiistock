<?php

namespace App\Repository;

use App\Entity\DimensionsEtiquettes;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method DimensionsEtiquettes|null find($id, $lockMode = null, $lockVersion = null)
 * @method DimensionsEtiquettes|null findOneBy(array $criteria, array $orderBy = null)
 * @method DimensionsEtiquettes[]    findAll()
 * @method DimensionsEtiquettes[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DimensionsEtiquettesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DimensionsEtiquettes::class);
    }

	/**
	 * @return DimensionsEtiquettes|null
	 * @throws NonUniqueResultException
	 */
    public function findOneDimension()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT de
            FROM App\Entity\DimensionsEtiquettes de
            "
        );
        return $query->getOneOrNullResult();
    }

    /**
     * @param bool $includeNullDimensions if true and dimension are not defined we include width and height (= 0) in returned array
     * @return array
     * @throws NonUniqueResultException
     */
    public function getDimensionArray(bool $includeNullDimensions = true) {
        /** @var DimensionsEtiquettes|null $dimension */
        $dimension = $this->findOneDimension();
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
        return $response;
    }

}
