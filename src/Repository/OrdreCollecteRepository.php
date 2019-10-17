<?php

namespace App\Repository;

use App\Entity\OrdreCollecte;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method OrdreCollecte|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrdreCollecte|null findOneBy(array $criteria, array $orderBy = null)
 * @method OrdreCollecte[]    findAll()
 * @method OrdreCollecte[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrdreCollecteRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, OrdreCollecte::class);
    }

    public function findOneByDemandeCollecte($collecte)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT a
            FROM App\Entity\OrdreCollecte a
            WHERE a.demandeCollecte = :collecte'
        )->setParameter('collecte', $collecte);
        return $query->getOneOrNullResult();
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
			"SELECT COUNT(o)
            FROM App\Entity\OrdreCollecte o
            WHERE o.utilisateur = :user"
		)->setParameter('user', $user);

		return $query->getSingleScalarResult();
	}

	public function findByFilter($params)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('c')
            ->from('App\Entity\Collecte', 'c');

        $countQuery = $countTotal = count($qb->getQuery()->getResult());

        $allDemandeDataTable = null;
        //Filter search
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->andWhere('c.objet LIKE :value')
                        ->setParameter('value', '%' . $search . '%');
                }
                $countQuery = count($qb->getQuery()->getResult());
            }
            $allDemandeDataTable = $qb->getQuery();
            if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
            if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));
        }
        //Filter by date
        if (!empty($params->get('dateMin'))) {
            $qb
                ->andWhere('c.date >= :dateMin')
                ->setParameter('dateMin', $params->get('dateMin'));
            $countQuery = count($qb->getQuery()->getResult());
            $allDemandeDataTable = $qb->getQuery();
        }
        if (!empty($params->get('dateMax'))) {
            $qb
                ->andWhere('c.date <= :dateMax')
                ->setParameter('dateMax', $params->get('dateMax'));
            $countQuery = count($qb->getQuery()->getResult());
            $allDemandeDataTable = $qb->getQuery();
        }
        //Filter by statut
        if (!empty($params->get('statut'))) {
            $qb
                ->join('c.statut', 's')
                ->andWhere('s.nom = :statut')
                ->setParameter('statut', $params->get('statut'));
            $countQuery = count($qb->getQuery()->getResult());
            $allDemandeDataTable = $qb->getQuery();
        }
        //Filter by user
        if (!empty($params->get('user'))) {
            $arrayUser = explode(',', $params->get('user'));
            $qb->join('c.demandeur', 'd');
            $qb
                ->andWhere('d.username IN (:user)')
                ->setParameter('user', $arrayUser);
            $countQuery = count($qb->getQuery()->getResult());
            $allDemandeDataTable = $qb->getQuery();
        }
        //Filter by type
        if (!empty($params->get('type'))) {
            $qb
                ->join('c.type', 't')
                ->andWhere('t.label = :type')
                ->setParameter('type', $params->get('type'));
            $countQuery = count($qb->getQuery()->getResult());
            $allDemandeDataTable = $qb->getQuery();
        }

        $query = $qb->getQuery();
        return ['data' => $query ? $query->getResult() : null ,
            'allDemandeDataTable' => $allDemandeDataTable ? $allDemandeDataTable->getResult() : null,
            'count' => $countQuery,
            'total' => $countTotal
        ];
    }
}
