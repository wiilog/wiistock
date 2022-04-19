<?php

namespace App\Repository\Transport;

use App\Entity\Transport\TransportRound;
use Doctrine\ORM\EntityRepository;

/**
 * @method TransportRound|null find($id, $lockMode = null, $lockVersion = null)
 * @method TransportRound|null findOneBy(array $criteria, array $orderBy = null)
 * @method TransportRound[]    findAll()
 * @method TransportRound[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransportRoundRepository extends EntityRepository {}
