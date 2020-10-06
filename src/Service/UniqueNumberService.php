<?php

namespace App\Service;

use App\Entity\Statut;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Entity;
use Exception;

class UniqueNumberService
{

    const DATE_COUNTER_FORMAT = 'YmdCCCC';

    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }


    /**
     * @param string $prefix - Prefix of the entity unique number => Available in choosen entity
     * @param string $format - Format of the entity unique number => Available in UniqueNumberService
     * @param Entity $entity - Choosen entity to generate unique number => Format Entity::class
     * @return string
     * @throws Exception
     */
    public function createUniqueNumber(string $prefix,
                                       string $format,
                                       Entity $entity): string {

        $date = new DateTime('now', new DateTimeZone('Europe/Paris'));
        $dateStr = $date->format('Ymd');
        $entityRepository = $this->entityManager->getRepository($entity);
        $lastNumber = $entityRepository->getLastNumberByPrefixAndDate($prefix, $dateStr);

        preg_match('/([^C]*)(C+)/', $format, $matches);
        if (empty($matches)) {
            throw new Exception('Invalid number format');
        }

        $dateFormat = $matches[1];
        $counterFormat = $matches[2];
        $counterLen = strlen($counterFormat);

        $lastCounter = (
            (!empty($lastNumber) && $counterLen <= strlen($lastNumber)) // TODO calculer la longueur du compteur d'un numéro unique
                ? (int) substr($lastNumber, -$counterLen, $counterLen)
                : 0
        );

        $currentCounterStr = sprintf("%0{$counterLen}u", $lastCounter + 1);
        $dateStr = !empty($dateFormat) ? $date->format($dateFormat) : '';
        $smartPrefix = !empty($prefix) ? ($prefix . '-') : '';

        return ($smartPrefix . $dateStr . $currentCounterStr);
    }
}
