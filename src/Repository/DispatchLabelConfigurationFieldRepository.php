<?php

namespace App\Repository;

use App\Entity\DispatchLabelConfigurationField;
use Doctrine\ORM\EntityRepository;

/**
 * @method DispatchLabelConfigurationField|null find($id, $lockMode = null, $lockVersion = null)
 * @method DispatchLabelConfigurationField|null findOneBy(array $criteria, array $orderBy = null)
 * @method DispatchLabelConfigurationField[]    findAll()
 * @method DispatchLabelConfigurationField[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DispatchLabelConfigurationFieldRepository extends EntityRepository {

}
