<?php

namespace App\Repository;

use App\Entity\FiltreSup;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method FiltreSup|null find($id, $lockMode = null, $lockVersion = null)
 * @method FiltreSup|null findOneBy(array $criteria, array $orderBy = null)
 * @method FiltreSup[]    findAll()
 * @method FiltreSup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FiltreSupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FiltreSup::class);
    }

	/**
	 * @param string $field
	 * @param string $page
	 * @param Utilisateur $user
	 * @return FiltreSup|null
	 * @throws NonUniqueResultException
	 */
    public function findOnebyFieldAndPageAndUser($field, $page, $user)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			/** @lang DQL */
			"SELECT fs
			FROM App\Entity\FiltreSup fs
			WHERE fs.field = :field
			AND fs.page = :page
			AND fs.user = :user"
		)->setParameters([
			'field' => $field,
			'page' => $page,
			'user' => $user
		]);

		return $query->getOneOrNullResult();
	}

	/**
	 * @param string $field
	 * @param string $page
	 * @param Utilisateur $user
	 * @return array
	 * @throws NonUniqueResultException
	 */
    public function getOnebyFieldAndPageAndUser($field, $page, $user)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
			/** @lang DQL */
			"SELECT fs.value
			FROM App\Entity\FiltreSup fs
			WHERE fs.field = :field
			AND fs.page = :page
			AND fs.user = :user"
		)->setParameters([
			'field' => $field,
			'page' => $page,
			'user' => $user
		]);

		$result = $query->getOneOrNullResult();
		dump($result);
		return $result ? $result['value'] : null;
	}

	/**
	 * @param string $page
	 * @param Utilisateur $user
	 * @return array
	 */
	public function getFieldAndValueByPageAndUser($page, $user)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT fs.field, fs.value
			FROM App\Entity\FiltreSup fs
			WHERE fs.page = :page
			AND fs.user = :user"
		)->setParameters([
			'page' => $page,
			'user' => $user
		]);

		return $query->execute();
	}
}
