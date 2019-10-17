<?php

namespace App\Repository;

use App\Entity\Demande;
use App\Entity\Livraison;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Demande|null find($id, $lockMode = null, $lockVersion = null)
 * @method Demande|null findOneBy(array $criteria, array $orderBy = null)
 * @method Demande[]    findAll()
 * @method Demande[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DemandeRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Demande::class);
    }

	/**
	 * @param Livraison $livraison
	 * @return Demande|null
	 * @throws \Doctrine\ORM\NonUniqueResultException
	 */
	public function findOneByLivraison($livraison)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			'SELECT d
            FROM App\Entity\Demande d
            WHERE d.livraison = :livraison'
		)->setParameter('livraison', $livraison);

		return $query->getOneOrNullResult();
	}

    public function findByUserAndNotStatus($user, $status)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT d
            FROM App\Entity\Demande d
            JOIN d.statut s
            WHERE s.nom <> :status AND d.utilisateur = :user"
        )->setParameters(['user' => $user, 'status' => $status]);

        return $query->execute();
    }

    public function findByStatutAndUser($statut, $user)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT d
            FROM App\Entity\Demande d
            WHERE d.statut = :statut AND d.utilisateur = :user"
        )->setParameters([
            'statut' => $statut,
            'user' => $user
        ]);
        return $query->execute();
    }

    public function countByEmplacement($emplacementId)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT COUNT(d)
            FROM App\Entity\Demande d
            JOIN d.destination dest
            WHERE dest.id = :emplacementId"
        )->setParameter('emplacementId', $emplacementId);

        return $query->getSingleScalarResult();
    }

    public function countByStatutAndUser($statut, $user)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery(
            "SELECT COUNT(d)
            FROM App\Entity\Demande d
            WHERE d.statut = :statut AND d.utilisateur = :user"
        )->setParameters([
            'statut' => $statut,
            'user' => $user,
        ]);

        return $query->getSingleScalarResult();
    }

    public function countByStatut($statut)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT COUNT(d)
            FROM App\Entity\Demande d
            WHERE d.statut = :statut "
        )->setParameter('statut', $statut);
        return $query->getSingleScalarResult();
    }

    public function findOneByPreparation($preparation)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT a
            FROM App\Entity\Demande a
            WHERE a.preparation = :preparation'
        )->setParameter('preparation', $preparation);
        return $query->getOneOrNullResult();
    }

	/**
	 * @param string $dateMin
	 * @param string $dateMax
	 * @return Demande[]|null
	 */
    public function findByDates($dateMin, $dateMax)
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT d
            FROM App\Entity\Demande d
            WHERE d.date BETWEEN :dateMin AND :dateMax'
        )->setParameters([
            'dateMin' => $dateMin,
            'dateMax' => $dateMax
        ]);
        return $query->execute();
    }

    public function getLastNumeroByPrefixeAndDate($prefixe, $date)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang DQL */
			'SELECT d.numero
			FROM App\Entity\Demande d
			WHERE d.numero LIKE :value
			ORDER BY d.date DESC'
		)->setParameter('value', $prefixe . $date . '%');

		$result = $query->execute();
		return $result ? $result[0]['numero'] : null;
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
			"SELECT COUNT(d)
            FROM App\Entity\Demande d
            WHERE d.utilisateur = :user"
		)->setParameter('user', $user);

		return $query->getSingleScalarResult();
	}

	public function findByFilter($params)
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        $qb
            ->select('d')
            ->from('App\Entity\Demande', 'd');

        $countQuery = $countTotal = count($qb->getQuery()->getResult());

        $allDemandeDataTable = null;
        //Filter search
        if (!empty($params)) {
            if (!empty($params->get('search'))) {
                $search = $params->get('search')['value'];
                if (!empty($search)) {
                    $qb
                        ->andWhere('d.numero LIKE :value')
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
                ->andWhere('d.date >= :dateMin')
                ->setParameter('dateMin', $params->get('dateMin'));
            $countQuery = count($qb->getQuery()->getResult());
            $allDemandeDataTable = $qb->getQuery();
        }
        if (!empty($params->get('dateMax'))) {
            $qb
                ->andWhere('d.date <= :dateMax')
                ->setParameter('dateMax', $params->get('dateMax'));
            $countQuery = count($qb->getQuery()->getResult());
            $allDemandeDataTable = $qb->getQuery();
        }
        //Filter by statut
        if (!empty($params->get('statut'))) {
            $qb
                ->join('d.statut', 's')
                ->andWhere('s.nom = :statut')
                ->setParameter('statut', $params->get('statut'));
            $countQuery = count($qb->getQuery()->getResult());
            $allDemandeDataTable = $qb->getQuery();
        }
        //Filter by user
        if (!empty($params->get('user'))) {
            $arrayUser = explode(',', $params->get('user'));
            $qb->join('d.utilisateur', 'u');
            $qb
                ->andWhere('u.username IN (:user)')
                ->setParameter('user', $arrayUser);
            $countQuery = count($qb->getQuery()->getResult());
            $allDemandeDataTable = $qb->getQuery();
        }
        //Filter by type
        if (!empty($params->get('type'))) {
            $qb
                ->join('d.type', 't')
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