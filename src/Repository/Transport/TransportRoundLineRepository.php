<?php

namespace App\Repository\Transport;

use App\Entity\Transport\TransportRoundLine;
use Doctrine\ORM\EntityRepository;

/**
 * @method TransportRoundLine|null find($id, $lockMode = null, $lockVersion = null)
 * @method TransportRoundLine|null findOneBy(array $criteria, array $orderBy = null)
 * @method TransportRoundLine[]    findAll()
 * @method TransportRoundLine[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransportRoundLineRepository extends EntityRepository {

}
