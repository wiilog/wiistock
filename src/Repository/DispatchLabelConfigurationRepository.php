<?php

namespace App\Repository;

use App\Entity\DispatchLabelConfiguration;
use Doctrine\ORM\EntityRepository;

/**
 * @method DispatchLabelConfiguration|null find($id, $lockMode = null, $lockVersion = null)
 * @method DispatchLabelConfiguration|null findOneBy(array $criteria, array $orderBy = null)
 * @method DispatchLabelConfiguration[]    findAll()
 * @method DispatchLabelConfiguration[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DispatchLabelConfigurationRepository extends EntityRepository {

}
