<?php

namespace App\Repository;

use App\Entity\SessionHistory;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<SessionHistory>
 *
 * @method SessionHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method SessionHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method SessionHistory[]    findAll()
 * @method SessionHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SessionRepository extends EntityRepository
{

}
