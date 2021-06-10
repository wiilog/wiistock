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

    public function getTemplateForSelect(){
        $qb = $this->createQueryBuilder("alert_template");

        $qb->select("alert_template.id AS id")
            ->addSelect("alert_template.name AS text");

        return $qb->getQuery()->getResult();
    }

}
