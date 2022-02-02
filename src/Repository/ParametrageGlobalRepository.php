<?php

namespace App\Repository;

use App\Entity\ParametrageGlobal;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use WiiCommon\Helper\Stream;

/**
 * @method ParametrageGlobal|null find($id, $lockMode = null, $lockVersion = null)
 * @method ParametrageGlobal|null findOneBy(array $criteria, array $orderBy = null)
 * @method ParametrageGlobal[]    findAll()
 * @method ParametrageGlobal[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ParametrageGlobalRepository extends EntityRepository
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
            ->keymap(fn(ParametrageGlobal $setting) => [$setting->getLabel(), $setting])
            ->toArray();
    }

    public function getUnusedLogo(ParametrageGlobal $logo, EntityManagerInterface $entityManager){
        $parametrageGlobalRepository = $entityManager->getRepository(ParametrageGlobal::class);
        return (
            $logo->getValue() != ParametrageGlobal::DEFAULT_WEBSITE_LOGO_VALUE &&
            $logo->getValue() != ParametrageGlobal::DEFAULT_EMAIL_LOGO_VALUE &&
            $logo->getValue() != ParametrageGlobal::DEFAULT_MOBILE_LOGO_HEADER_VALUE &&
            $logo->getValue() != ParametrageGlobal::DEFAULT_MOBILE_LOGO_LOGIN_VALUE &&
            $logo->getValue() != $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::WEBSITE_LOGO) &&
            $logo->getValue() != $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::EMAIL_LOGO) &&
            $logo->getValue() != $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::MOBILE_LOGO_HEADER) &&
            $logo->getValue() != $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::MOBILE_LOGO_LOGIN)
        );
    }
}
