<?php

namespace App\Repository;

use App\Entity\ReceptionPackLine;
use Doctrine\ORM\EntityRepository;

/**
 * @method ReceptionPackLine|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReceptionPackLine|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReceptionPackLine[]    findAll()
 * @method ReceptionPackLine[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReceptionPackLineRepository extends EntityRepository {

}
