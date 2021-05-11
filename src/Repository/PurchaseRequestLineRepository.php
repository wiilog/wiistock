<?php

namespace App\Repository;

use App\Entity\PurchaseRequestLine;
use Doctrine\ORM\EntityRepository;

/**
 * @method PurchaseRequestLine|null find($id, $lockMode = null, $lockVersion = null)
 * @method PurchaseRequestLine|null findOneBy(array $criteria, array $orderBy = null)
 * @method PurchaseRequestLine[]    findAll()
 * @method PurchaseRequestLine[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PurchaseRequestLineRepository extends EntityRepository
{

}
