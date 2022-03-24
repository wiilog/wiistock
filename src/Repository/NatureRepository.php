<?php

namespace App\Repository;

use App\Entity\Nature;
use App\Helper\QueryCounter;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * @method Nature|null find($id, $lockMode = null, $lockVersion = null)
 * @method Nature|null findOneBy(array $criteria, array $orderBy = null)
 * @method Nature[]    findAll()
 * @method Nature[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NatureRepository extends EntityRepository
{
    public function findByParams(InputBag $params): array {
        $qb = $this->createQueryBuilder('nature');
        $total = QueryCounter::count($qb, 'nature');

        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $exprBuilder = $qb->expr();
                    $qb
                        ->andWhere('(' .
                            $exprBuilder->orX(
                                "nature.label LIKE :value",
                                "nature.code LIKE :value",
                                "nature.description LIKE :value"
                            )
                            . ')')
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->get('columns')[$params->get('order')[0]['column']]['data'];
                    if(property_exists(Nature::class, $column)) {
                        $qb->orderBy('nature.' . $column, $order);
                    }
                }
            }
        }

        $countFiltered = QueryCounter::count($qb, 'nature');

        if ($params->getInt('start')) {
            $qb->setFirstResult($params->getInt('start'));
        }
        if ($params->getInt('length')) {
            $qb->setMaxResults($params->getInt('length'));
        }

        return [
            'data' => $qb->getQuery()->getResult(),
            'count' => $countFiltered,
            'total' => $total,
        ];
    }

    public function getAllowedNaturesIdByLocation() {
        return $this->createQueryBuilder('nature')
            ->select('nature.id AS nature_id')
            ->addSelect('location.id AS location_id')
            ->join('nature.emplacements', 'location')
            ->getQuery()
            ->getResult();
    }

    public function countUsedById($id)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            "SELECT COUNT(n)
            FROM App\Entity\Nature n
            LEFT JOIN n.packs pack
            WHERE pack.nature = :id
           "
        )->setParameter('id', $id);

        return $query->getSingleScalarResult();
    }

    public function countAll()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            "SELECT COUNT(n)
            FROM App\Entity\Nature n
           "
        );

        return $query->getSingleScalarResult();
    }

    public function findAllLabels() {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        /** @lang DQL */
            "SELECT n.label as label
            FROM App\Entity\Nature n
           "
        );
        return array_map(function(array $nature) {
            return $nature['label'];
        }, $query->getResult());
    }
}
