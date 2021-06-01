<?php

namespace App\Repository\IOT;

use App\Entity\IOT\AlertTemplate;
use Doctrine\ORM\EntityRepository;

/**
 * @method AlertTemplate|null find($id, $lockMode = null, $lockVersion = null)
 * @method AlertTemplate|null findOneBy(array $criteria, array $orderBy = null)
 * @method AlertTemplate[]    findAll()
 * @method AlertTemplate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AlertTemplateRepository extends EntityRepository
{
}
