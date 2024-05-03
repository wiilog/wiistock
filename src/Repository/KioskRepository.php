<?php

namespace App\Repository;

use App\Entity\Kiosk;
use App\Helper\QueryBuilderHelper;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * @method Kiosk|null find($id, $lockMode = null, $lockVersion = null)
 * @method Kiosk|null findOneBy(array $criteria, array $orderBy = null)
 * @method Kiosk[]    findAll()
 * @method Kiosk[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class KioskRepository extends EntityRepository
{
    public function save(Kiosk $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Kiosk $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByParams(InputBag $params): array
    {

        $queryBuilder = $this->createQueryBuilder('kiosk');
        $total = QueryBuilderHelper::count($queryBuilder, 'kiosk');

        if (!empty($params)) {
            if (!empty($params->all('search'))) {
                $search = $params->all('search')['value'];
                if (!empty($search)) {
                    $exprBuilder = $queryBuilder->expr();
                    $queryBuilder
                        ->andWhere($exprBuilder->orX(
                            'kiosk.name LIKE :value',
                            'search_picking_type.label LIKE :value',
                            'search_requester.username LIKE :value',
                            'search_picking_location.label LIKE :value',
                        ))
                        ->leftJoin('kiosk.pickingType', 'search_picking_type')
                        ->leftJoin('kiosk.requester', 'search_requester')
                        ->leftJoin('kiosk.pickingLocation', 'search_picking_location')
                        ->setParameter('value', '%' . $search . '%');
                }
            }

            if (!empty($params->all('order'))) {
                $order = $params->all('order')[0]['dir'];
                if (!empty($order)) {
                    $column = $params->all('columns')[$params->all('order')[0]['column']]['data'];

                    switch ($column) {
                        case 'name':
                            $queryBuilder->orderBy("kiosk.name", $order);
                            break;
                        case 'pickingType':
                            $queryBuilder
                                ->leftJoin('kiosk.pickingType', 'order_picking_type')
                                ->orderBy("order_picking_type.label", $order);
                            break;
                        case 'requester':
                            $queryBuilder
                                ->leftJoin('kiosk.requester', 'order_requester')
                                ->orderBy("order_requester.username", $order);
                            break;
                        case 'pickingLocation':
                            $queryBuilder
                                ->leftJoin('kiosk.pickingLocation', 'order_picking_location')
                                ->orderBy("order_picking_location.label", $order);
                            break;
                        default:
                            if (property_exists(Kiosk::class, $column)) {
                                $queryBuilder->orderBy('kiosk.' . $column, $order);
                            }
                            break;
                    }
                }
            }
        }

        $countFiltered = QueryBuilderHelper::count($queryBuilder, 'kiosk');

        if ($params->getInt('start')) {
            $queryBuilder->setFirstResult($params->getInt('start'));
        }

        if ($params->getInt('length')) {
            $queryBuilder->setMaxResults($params->getInt('length'));
        }

        return [
            'data' => $queryBuilder->getQuery()->getResult(),
            'count' => $countFiltered,
            'total' => $total
        ];
    }

//    /**
//     * @return KioskToken[] Returns an array of KioskToken objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('k')
//            ->andWhere('k.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('k.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?KioskToken
//    {
//        return $this->createQueryBuilder('k')
//            ->andWhere('k.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
