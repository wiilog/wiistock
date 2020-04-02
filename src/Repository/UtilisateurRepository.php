<?php

namespace App\Repository;

use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;
use Laminas\Code\Scanner\Util;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;

/**
 * @method Utilisateur|null find($id, $lockMode = null, $lockVersion = null)
 * @method Utilisateur|null findOneBy(array $criteria, array $orderBy = null)
 * @method Utilisateur[]    findAll()
 * @method Utilisateur[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UtilisateurRepository extends ServiceEntityRepository implements UserLoaderInterface
{


    private const DtToDbLabels = [
        'Nom d\'utilisateur' => 'username',
        'Email' => 'email',
        'Dropzone' => 'dropzone',
        'Dernière connexion' => 'lastLogin',
        'Rôle' => 'role',
    ];

	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, Utilisateur::class);
	}

	public function countByEmail($email)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang DQL */
			"SELECT COUNT(u)
            FROM App\Entity\Utilisateur u
            WHERE u.email = :email"
		)->setParameter('email', $email);

		return $query->getSingleScalarResult();
	}

	public function countByUsername($username)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang DQL */
			"SELECT COUNT(u)
			FROM App\Entity\Utilisateur u
			WHERE u.username = :username"
		)->setParameter('username', $username);

		return $query->getSingleScalarResult();
	}

	public function countApiKey($apiKey)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang DQL */
			"SELECT COUNT(u)
            FROM App\Entity\Utilisateur u
            WHERE u.apiKey = :apiKey"
		)->setParameter('apiKey', $apiKey);

		return $query->getSingleScalarResult();
	}

	public function getIdAndUsername()
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang DQL */
			"SELECT u.id, u.username
            FROM App\Entity\Utilisateur u
            ORDER BY u.username
            "
		);

		return $query->execute();
	}

	public function getIdAndLibelleBySearch($search)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			"SELECT u.id, u.username as text
          FROM App\Entity\Utilisateur u
          WHERE u.username LIKE :search"
		)->setParameter('search', '%' . $search . '%');

		return $query->execute();
	}

    /**
     * @param $search
     * @return Utilisateur|null
     * @throws NonUniqueResultException
     */
	public function findOneByUsername($search)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			/** @lang DQL */
			"SELECT u
          FROM App\Entity\Utilisateur u
          WHERE u.username = :search"
		)->setParameter('search', $search);

		return $query->getOneOrNullResult();
	}

	/**
	 * @param $roleId
	 * @return int
	 * @throws NoResultException
	 * @throws NonUniqueResultException
	 */
	public function countByRoleId($roleId)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang DQL */
			"SELECT COUNT(u)
            FROM App\Entity\Utilisateur u
            JOIN u.role r
            WHERE r.id = :roleId"
		)->setParameter('roleId', $roleId);
		return $query->getSingleScalarResult();
	}

	public function findOneByMail($mail)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang DQL */
			"SELECT u
            FROM App\Entity\Utilisateur u
            WHERE u.email = :email"
		)->setParameter('email', $mail);

		return $query->getOneOrNullResult();
	}

	public function findOneByToken($token)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang DQL */
			"SELECT u
            FROM App\Entity\Utilisateur u
            WHERE u.token = :token"
		)->setParameter('token', $token);

		return $query->getOneOrNullResult();
	}

    /**
     * @param $key
     * @return Utilisateur | null
     * @throws NonUniqueResultException
     */
	public function findOneByApiKey($key)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang DQL */
			"SELECT u
            FROM App\Entity\Utilisateur u
            WHERE u.apiKey = :key"
		)->setParameter('key', $key);

		return $query->getOneOrNullResult();
	}

	public function loadUserByUsername($username)
	{
		return $this->createQueryBuilder('u')
			->where('u.email = :username AND u.status = 1')
			->setParameter('username', $username)
			->getQuery()
			->getOneOrNullResult();
	}

	public function findByParams($params = null)
	{
		$em = $this->getEntityManager();
		$qb = $em->createQueryBuilder();

		$qb
			->select('a')
			->from('App\Entity\Utilisateur', 'a');

		// prise en compte des paramètres issus du datatable
		if (!empty($params)) {
			if (!empty($params->get('start'))) $qb->setFirstResult($params->get('start'));
			if (!empty($params->get('length'))) $qb->setMaxResults($params->get('length'));

            if (!empty($params->get('order'))) {
                $order = $params->get('order')[0]['dir'];

                if (!empty($order)) {
                	$column = $params->get('columns')[$params->get('order')[0]['column']]['data'];

                	switch ($column) {
						case 'Dropzone':
							$qb
								->leftJoin('a.dropzone', 'd_order')
								->orderBy('d_order.label', $order);
							break;
						default:
                    		$qb->orderBy('a.' . self::DtToDbLabels[$column], $order);
					}
                }
            }

			if (!empty($params->get('search'))) {
				$search = $params->get('search')['value'];
				if (!empty($search)) {
					$qb
						->leftJoin('a.dropzone', 'd_search')
						->andWhere('a.username LIKE :value OR a.email LIKE :value OR d_search.label LIKE :value')
						->setParameter('value', '%' . $search . '%');
				}
			}
		}

		$query = $qb->getQuery();

		return $query->getResult();
	}

	public function countAll()
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang DQL */
			"SELECT COUNT(a)
            FROM App\Entity\Utilisateur a
           "
		);

		return $query->getSingleScalarResult();
	}

	public function findAllSorted()
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			/** @lang DQL */
			"SELECT u FROM App\Entity\Utilisateur u
            ORDER BY u.username
            "
		);

		return $query->execute();
	}
}
