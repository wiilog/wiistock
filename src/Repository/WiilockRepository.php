<?php

namespace App\Repository;

use App\Entity\Wiilock;
use Doctrine\ORM\EntityRepository;

/**
 * @method Wiilock|null find($id, $lockMode = null, $lockVersion = null)
 * @method Wiilock|null findOneBy(array $criteria, array $orderBy = null)
 * @method Wiilock[]    findAll()
 * @method Wiilock[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WiilockRepository extends EntityRepository
{

}
