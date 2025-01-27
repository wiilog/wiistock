<?php

namespace App\Repository;

use App\Entity\Setting;
use Doctrine\ORM\EntityRepository;
use WiiCommon\Helper\Stream;

/**
 * @method Setting|null find($id, $lockMode = null, $lockVersion = null)
 * @method Setting|null findOneBy(array $criteria, array $orderBy = null)
 * @method Setting[]    findAll()
 * @method Setting[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SettingRepository extends EntityRepository
{

    public function findByLabel($labels) {
        if(!is_array($labels)) {
            $labels = [$labels];
        }

        $results = $this->createQueryBuilder("setting")
            ->andWhere("setting.label IN (:labels)")
            ->setParameter("labels", $labels)
            ->getQuery()
            ->getResult();

        return Stream::from($results)
            ->keymap(fn(Setting $setting) => [$setting->getLabel(), $setting])
            ->toArray();
    }
}
