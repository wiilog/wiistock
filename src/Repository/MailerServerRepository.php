<?php

namespace App\Repository;

use App\Entity\MailerServer;
use Doctrine\ORM\EntityRepository;

/**
 * @method MailerServer|null find($id, $lockMode = null, $lockVersion = null)
 * @method MailerServer|null findOneBy(array $criteria, array $orderBy = null)
 * @method MailerServer[]    findAll()
 * @method MailerServer[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MailerServerRepository extends EntityRepository {}
