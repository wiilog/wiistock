<?php

namespace App\Repository\Transport;

use App\Entity\Transport\TransportRequestContact;
use Doctrine\ORM\EntityRepository;

/**
 * @method TransportRequestContact|null find($id, $lockMode = null, $lockVersion = null)
 * @method TransportRequestContact|null findOneBy(array $criteria, array $orderBy = null)
 * @method TransportRequestContact[]    findAll()
 * @method TransportRequestContact[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransportRequestContactRepository extends EntityRepository {}
