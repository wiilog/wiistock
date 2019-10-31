<?php

namespace App\Repository;

use App\Entity\Arrivage;
use App\Entity\Colis;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

use Doctrine\ORM\NonUniqueResultException;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Colis|null find($id, $lockMode = null, $lockVersion = null)
 * @method Colis|null findOneBy(array $criteria, array $orderBy = null)
 * @method Colis[]    findAll()
 * @method Colis[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ColisRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Colis::class);
    }

	/**
	 * @param string $code
	 * @return Colis|null
	 * @throws NonUniqueResultException
	 */
    public function findOneByCode($code)
    {
        $em = $this->getEntityManager();
        $query = $em->createQuery('
            SELECT c
            FROM App\Entity\Colis c
            WHERE c.code = :code'
        )->setParameter('code', $code);
        return $query->getOneOrNullResult();
    }

	/**
	 * @param string $prefix
	 * @return mixed
	 */
	public function getHighestCodeByPrefix($prefix)
	{
		$em = $this->getEntityManager();
		$query = $em->createQuery(
		/** @lang DQL */
			"SELECT c.code
			FROM App\Entity\Colis c
			WHERE c.code LIKE :prefix
			ORDER BY c.code DESC
			")
			->setParameter('prefix', $prefix . '%')
			->setMaxResults(1);

		$result = $query->execute();

		return $result ? $result[0]['code'] : null;;
	}

}