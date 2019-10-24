<?php

namespace App\Repository;

use App\Entity\Manutention;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
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
	 * @throws NonUniqueResultException
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

	public function findByParamAndFilters($params, $filters)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
			->select('m')
            ->from('App\Entity\Manutention', 'm');

        $countTotal = count($qb->getQuery()->getResult());

        // filtres sup
        foreach ($filters as $filter) {
            switch($filter['field']) {
                case 'statut':
                    $qb
                        ->join('m.statut', 's')
                        ->andWhere('s.nom = :statut')
                        ->setParameter('statut', $filter['value']);
                    break;
                case 'utilisateurs':
                    $value = explode(',', $filter['value']);
                    $qb
                        ->join('m.demandeur', 'd')
                        ->andWhere("d.username in (:username)")
                        ->setParameter('username', $value);
                    break;
                case 'dateMin':
                    $qb->andWhere('m.date >= :dateMin')
                        ->setParameter('dateMin', $filter['value']);
                    break;
                case 'dateMax':
                    $qb->andWhere('m.date <= :dateMax')
                        ->setParameter('dateMax', $filter['value']);
                    break;
            }
        }

        // compte éléments filtrés
		$countFiltered = empty($filters) ? $countTotal : count($qb->getQuery()->getResult());

		//Filter search
		if (!empty($params)) {
			if (!empty($params->get('search'))) {
				$search = $params->get('search')['value'];
				if (!empty($search)) {
					$qb
						->andWhere('m.libelle LIKE :value OR m.date LIKE :value')
						->setParameter('value', '%' . $search . '%');
				}
			}
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
