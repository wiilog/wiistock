<?php

namespace App\Repository;

use App\Entity\Arrivage;
use App\Entity\Colis;
use App\Entity\MouvementTraca;
use App\Entity\Nature;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Colis|null find($id, $lockMode = null, $lockVersion = null)
 * @method Colis|null findOneBy(array $criteria, array $orderBy = null)
 * @method Colis[]    findAll()
 * @method Colis[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ColisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Colis::class);
    }

    /**
     * @param DateTime $dateMin
     * @param DateTime $dateMax
     * @return Arrivage[]|null
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countByDates(DateTime $dateMin, DateTime $dateMax)
    {
        return $this->createQueryBuilder('colis')
            ->join('colis.arrivage', 'arrivage')
            ->where('arrivage.date BETWEEN :dateMin AND :dateMax')
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax
            ])
            ->select('COUNT(arrivage)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getColisNaturesOnLocationCluster(array $locations, array $naturesFilter = []) {
        $entityManager = $this->getEntityManager();
        $mouvementTracaRepository = $entityManager->getRepository(MouvementTraca::class);
        $mouvementTracaOnClusterIds = $mouvementTracaRepository->getTrackingIdsGroupedByColis(['currentLocationsFilter' => $locations]);
        $mouvementTracaOnClusterColis = $mouvementTracaRepository->getColisById($mouvementTracaOnClusterIds);
        $firstMouvementsForColis = $mouvementTracaRepository->getTrackingIdsGroupedByColis(['colisFilter' => $mouvementTracaOnClusterColis, 'last' => false]);
        $queryBuilder = $this
            ->createQueryBuilder('colis')
            ->select('nature.id as natureId')
            ->addSelect('nature.label as natureLabel')
            ->addSelect('mouvementTraca.datetime AS dateTime')
            ->addSelect('location.id AS locationId')
            ->addSelect('location.label AS locationLabel')
            ->addSelect('location.dateMaxTime AS locationDateMaxTime')
            ->join('colis.nature', 'nature')
            ->join(MouvementTraca::class, 'mouvementTraca', 'WITH', 'mouvementTraca.colis = colis.code')
            ->join('mouvementTraca.type', 'type')
            ->join('mouvementTraca.emplacement', 'location')
            ->where('mouvementTraca.id IN (:mouvementTracaIds)')
            ->andWhere('type.nom = :typeDepose')
            ->setParameter('typeDepose', MouvementTraca::TYPE_DEPOSE)
            ->setParameter('mouvementTracaIds', $firstMouvementsForColis, Connection::PARAM_STR_ARRAY);

        if (!empty($naturesFilter)) {
            $queryBuilder
                ->andWhere('nature.id IN (:naturesFilter)')
                ->setParameter(
                    'naturesFilter',
                    array_map(function (Nature $nature) {
                        return $nature->getId();
                    }, $naturesFilter),
                    Connection::PARAM_STR_ARRAY
                );
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }
}
