<?php

namespace App\Repository;

use App\Entity\ParametreRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method ParametreRole|null find($id, $lockMode = null, $lockVersion = null)
 * @method ParametreRole|null findOneBy(array $criteria, array $orderBy = null)
 * @method ParametreRole[]    findAll()
 * @method ParametreRole[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ParametreRoleRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ParametreRole::class);
    }

    public function findOneByRoleAndParam($role, $param)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			"SELECT pr
			FROM App\Entity\ParametreRole pr
			WHERE pr.role = :role AND pr.parametre = :param
            "
		)->setParameters(['role' => $role, 'param' => $param]);

		return $query->getOneOrNullResult();
	}

	public function getValueByRoleAndParam($role, $param)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			"SELECT pr.value
			FROM App\Entity\ParametreRole pr
			WHERE pr.role = :role AND pr.parametre = :param
            "
		)->setParameters(['role' => $role, 'param' => $param]);

		$result = $query->execute();
		return $result ? $result[0]['value'] : null;
	}


}
