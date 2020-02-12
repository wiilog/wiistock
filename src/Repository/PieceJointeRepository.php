<?php

namespace App\Repository;

use App\Entity\PieceJointe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method PieceJointe|null find($id, $lockMode = null, $lockVersion = null)
 * @method PieceJointe|null findOneBy(array $criteria, array $orderBy = null)
 * @method PieceJointe[]    findAll()
 * @method PieceJointe[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PieceJointeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PieceJointe::class);
    }


    public function findOneByFileName($filename)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			"SELECT pj
           FROM App\Entity\PieceJointe pj
           WHERE pj.fileName = :filename"
		)->setParameter('filename', $filename);
		;
		return $query->getResult();
	}

	public function findOneByFileNameAndLitigeId($filename, $litigeId)
	{
		$entityManager = $this->getEntityManager();
		$query = $entityManager->createQuery(
			/** @lang  DQL */
			"SELECT pj
           FROM App\Entity\PieceJointe pj
           WHERE pj.fileName = :filename AND pj.litige = :litigeId"
		)->setParameters(['filename' => $filename, 'litigeId' => $litigeId]);

		return $query->getResult();
	}

}
