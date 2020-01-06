<?php

namespace App\Repository;

use App\Entity\MailerServer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method MailerServer|null find($id, $lockMode = null, $lockVersion = null)
 * @method MailerServer|null findOneBy(array $criteria, array $orderBy = null)
 * @method MailerServer[]    findAll()
 * @method MailerServer[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MailerServerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MailerServer::class);
    }

	/**
	 * @return MailerServer|null
	 * @throws \Doctrine\ORM\NonUniqueResultException
	 */
    public function findOneMailerServer()
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            "SELECT m
            FROM App\Entity\MailerServer m
            "
        );
        return $query->getOneOrNullResult();
    }

}
