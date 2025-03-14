<?php

namespace App\Repository;

use App\Entity\Setting;
use Doctrine\ORM\EntityRepository;
use WiiCommon\Helper\Stream;

class SettingRepository extends EntityRepository {

    /**
     * @param string[]|string $labels
     * @return array<string, Setting>
     */
    public function findByLabel(array|string $labels): array {
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
