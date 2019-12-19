<?php

namespace App\Repository;

use App\Entity\Arrivage;
use App\Entity\Urgence;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;

use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Urgence|null find($id, $lockMode = null, $lockVersion = null)
 * @method Urgence|null findOneBy(array $criteria, array $orderBy = null)
 * @method Urgence[]    findAll()
 * @method Urgence[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UrgenceRepository extends ServiceEntityRepository
{

    private const DtToDbLabels = [
        'commande' => 'commande',
        "start" => 'dateEnd',
        "end" => 'dateStart',
    ];

    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Urgence::class);
    }

    /**
     * @param Arrivage $arrivage
     * @return int
     * @throws NonUniqueResultException
     */
    public function countByArrivageData(Arrivage $arrivage)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
        /** @lang DQL */
            "SELECT COUNT(u) 
                FROM App\Entity\Urgence u
                WHERE u.dateStart <= :date AND u.dateEnd >= :date AND u.commande LIKE :commande"
        )->setParameters([
            'date' => $arrivage->getDate(),
            'commande' => $arrivage->getNumeroBL()
        ]);

        return $query->getSingleScalarResult();
    }

    public function findByParams($params)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('u')
            ->from('App\Entity\Urgence', 'u');

        $countTotal = count($qb->getQuery()->getResult());
        //Filter search
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->andWhere('u.commande LIKE :value OR u.dateStart LIKE :value OR u.dateEnd LIKE :value')
                        ->setParameter('value', '%' . $search . '%');
                }
            }
            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];
                if (!empty($order)) {
                    $column = self::DtToDbLabels[$params->get('columns')[$params->get('order')[0]['column']]['data']];
                    $qb
                        ->orderBy('u.' . $column, $order);
                }
            }
        }

        // compte éléments filtrés
        $countFiltered = count($qb->getQuery()->getResult());

        if ($params) {
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }

        $query = $qb->getQuery();

        return [
            'data' => $query ? $query->getResult() : null,
            'count' => $countFiltered,
            'total' => $countTotal
        ];
    }
}
