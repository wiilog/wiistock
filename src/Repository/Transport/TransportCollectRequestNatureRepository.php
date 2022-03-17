<?php

namespace App\Repository\Transport;

use App\Entity\Transport\TransportCollectRequestNature;
use Doctrine\ORM\EntityRepository;

/**
 * @method TransportCollectRequestNature|null find($id, $lockMode = null, $lockVersion = null)
 * @method TransportCollectRequestNature|null findOneBy(array $criteria, array $orderBy = null)
 * @method TransportCollectRequestNature[]    findAll()
 * @method TransportCollectRequestNature[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransportCollectRequestNatureRepository extends EntityRepository {}
