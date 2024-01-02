<?php

namespace App\Repository;

use App\Entity\ProductionRequest;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<ProductionRequest>
 *
 * @method ProductionRequest|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductionRequest|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProductionRequest[]    findAll()
 * @method ProductionRequest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductionRequestRepository extends EntityRepository {}
