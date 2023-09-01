<?php

namespace App\Repository;

use App\Entity\UserSession;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<UserSessionRepository>
 *
 * @method UserSession|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserSession|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserSession[]    findAll()
 * @method UserSession[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserSessionRepository extends EntityRepository
{
}
