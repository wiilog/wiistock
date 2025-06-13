<?php

namespace App\Repository\Security;

use App\Entity\Security\AccessToken;
use App\Entity\Security\AccessTokenTypeEnum;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityRepository;
use DateTime;

/**
 * @extends EntityRepository<AccessToken>
 */
class AccessTokenRepository extends EntityRepository {

    /**
     * @return array<AccessToken>
     */
    public function findActiveOn(DateTime            $on,
                                 AccessTokenTypeEnum $type,
                                 Utilisateur         $owner): array {
        return $this->createQueryBuilder('accessToken')
            ->andWhere('accessToken.type = :type')
            ->andWhere('accessToken.owner = :owner')
            ->andWhere('accessToken.expireAt IS NULL OR accessToken.expireAt > :on')
            ->setParameter('type', $type)
            ->setParameter('owner', $owner)
            ->setParameter('on', $on)
            ->getQuery()
            ->getResult();
    }
}
