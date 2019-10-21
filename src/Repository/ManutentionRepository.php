<?php

namespace App\Repository;

use App\Entity\Manutention;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Manutention|null find($id, $lockMode = null, $lockVersion = null)
 * @method Manutention|null findOneBy(array $criteria, array $orderBy = null)
 * @method Manutention[]    findAll()
 * @method Manutention[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ManutentionRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Manutention::class);
    }

    public function countByStatut($statut){
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
        	/** @lang DQL */
            "SELECT COUNT(m)
            FROM App\Entity\Manutention m
            WHERE m.statut = :statut 
           "
            )->setParameter('statut', $statut);
        return $query->getSingleScalarResult(); 
    }

	/**
	 * @param Utilisateur $user
	 * @return int
	 * @throws \Doctrine\ORM\NonUniqueResultException
	 */
	public function countByUser($user)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT COUNT(m)
            FROM App\Entity\Manutention m
            WHERE m.demandeur = :user"
		)->setParameter('user', $user);

		return $query->getSingleScalarResult();
	}

	public function findByFilter($params)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('m')
            ->from('App\Entity\Manutention', 'm');

        $countQuery = $countTotal = count($qb->getQuery()->getResult());

        $allManutentionDataTable = null;
        //Filter search
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->andWhere('m.libelle LIKE :value OR m.date LIKE :value')
                        ->setParameter('value', '%' . $search . '%');
                }
                $countQuery = count($qb->getQuery()->getResult());
            }
            $allManutentionDataTable = $qb->getQuery();
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }
        //Filter by date
        if (!empty($params->get('dateMin'))) {
            $qb
                ->andWhere('m.date >= :dateMin')
                ->setParameter('dateMin', $params->get('dateMin'));
            $countQuery = count($qb->getQuery()->getResult());
            $allManutentionDataTable = $qb->getQuery();
        }
        if (!empty($params->get('dateMax'))) {
            $qb
                ->andWhere('m.date <= :dateMax')
                ->setParameter('dateMax', $params->get('dateMax'));
            $countQuery = count($qb->getQuery()->getResult());
            $allManutentionDataTable = $qb->getQuery();
        }
        //Filter by statut
        if (!empty($params->get('statut'))) {
            $qb
                ->join('m.statut', 's')
                ->andWhere('s.nom = :statut')
                ->setParameter('statut', $params->get('statut'));
            $countQuery = count($qb->getQuery()->getResult());
            $allManutentionDataTable = $qb->getQuery();
        }
        //Filter by user
        if (!empty($params->get('user'))) {
            $arrayUser = explode(',', $params->get('user'));
            $qb->join('m.demandeur', 'u');
            $qb
                ->andWhere('u.username IN (:user)')
                ->setParameter('user', $arrayUser);
            $countQuery = count($qb->getQuery()->getResult());
            $allManutentionDataTable = $qb->getQuery();
        }

        $query = $qb->getQuery();
        return ['data' => $query ? $query->getResult() : null ,
            'allManutentionDatatable' => $allManutentionDataTable ? $allManutentionDataTable->getResult() : null,
            'count' => $countQuery,
            'total' => $countTotal
        ];
    }
}
