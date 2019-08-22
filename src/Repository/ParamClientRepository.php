<?php

namespace App\Repository;

use App\Entity\ParamClient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method ParamClient|null find($id, $lockMode = null, $lockVersion = null)
 * @method ParamClient|null findOneBy(array $criteria, array $orderBy = null)
 * @method ParamClient[]    findAll()
 * @method ParamClient[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ParamClientRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ParamClient::class);
    }

	/**
	 * @return ParamClient|null
	 * @throws \Doctrine\ORM\NonUniqueResultException
	 */
	public function findOne()
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			"SELECT pc
            FROM App\Entity\ParamClient pc
            "
		);
		return $query->getOneOrNullResult();
	}
}
