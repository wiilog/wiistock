<?php

namespace App\Repository\IOT;

use App\Entity\IOT\Pairing;
use Doctrine\ORM\EntityRepository;

/**
 * @method Pairing|null find($id, $lockMode = null, $lockVersion = null)
 * @method Pairing|null findOneBy(array $criteria, array $orderBy = null)
 * @method Pairing[]    findAll()
 * @method Pairing[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PairingRepository extends EntityRepository
{
}
