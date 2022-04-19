<?php

namespace App\Repository;

use App\Entity\Setting;
use Doctrine\ORM\EntityManagerInterface;
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
    public function getOneParamByLabel($label) {
        $result = $this->createQueryBuilder('parameter')
            ->select('parameter.value')
            ->andWhere('parameter.label LIKE :label')
            ->setMaxResults(1)
            ->setParameter('label', $label)
            ->getQuery()
            ->getOneOrNullResult();

        return $result ? $result['value'] : null;
    }

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

    public function getUnusedLogo(Setting $logo, EntityManagerInterface $entityManager){
        $settingRepository = $entityManager->getRepository(Setting::class);
        return (
            $logo->getValue() != Setting::DEFAULT_WEBSITE_LOGO_VALUE &&
            $logo->getValue() != Setting::DEFAULT_EMAIL_LOGO_VALUE &&
            $logo->getValue() != Setting::DEFAULT_MOBILE_LOGO_HEADER_VALUE &&
            $logo->getValue() != Setting::DEFAULT_MOBILE_LOGO_LOGIN_VALUE &&
            $logo->getValue() != $settingRepository->getOneParamByLabel(Setting::FILE_WEBSITE_LOGO) &&
            $logo->getValue() != $settingRepository->getOneParamByLabel(Setting::FILE_EMAIL_LOGO) &&
            $logo->getValue() != $settingRepository->getOneParamByLabel(Setting::FILE_MOBILE_LOGO_HEADER) &&
            $logo->getValue() != $settingRepository->getOneParamByLabel(Setting::FILE_MOBILE_LOGO_LOGIN)
        );
    }
}
