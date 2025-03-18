<?php

namespace App\Repository\Security;

use App\Entity\Security\AccessToken;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<AccessToken>
 */
class AccessTokenRepository extends EntityRepository {
}
