<?php

namespace App\Service;

use DateTime;
use DateTimeZone;
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
     * getLastNumberByPrefixAndDate() function must be implemented in current entity repository with $prefix and $date params
     * @param EntityManagerInterface $entityManager
     * @param string $prefix - Prefix of the entity unique number => Available in choosen entity
     * @param string $format - Format of the entity unique number => Available in UniqueNumberService
     * @param string $entity - Choosen entity to generate unique number => Format Entity::class
     * @return string
     * @throws Exception
     */
    public function createUniqueNumber(EntityManagerInterface $entityManager,
                                       string $prefix,
                                       string $entity,
                                       string $format = UniqueNumberService::DATE_COUNTER_FORMAT): string {

        $date = new DateTime('now', new DateTimeZone('Europe/Paris'));
        $dateStr = $date->format('Ymd');
        $entityRepository = $entityManager->getRepository($entity);

        if (!method_exists($entityRepository, 'getLastNumberByDate')) {
            throw new Exception("Undefined getLastNumberByDate for repository of $entity");
        }

        $lastNumber = $entityRepository->getLastNumberByDate($dateStr, $prefix);

        preg_match('/([^C]*)(C+)/', $format, $matches);
        if (empty($matches)) {
            throw new Exception('Invalid number format');
        }

        $dateFormat = $matches[1];
        $counterFormat = $matches[2];
        $counterLen = strlen($counterFormat);

        $lastCounter = (
            (!empty($lastNumber) && $counterLen <= strlen($lastNumber))
                ? (int) substr($lastNumber, -$counterLen, $counterLen)
                : 0
        );

        $currentCounterStr = sprintf("%0{$counterLen}u", $lastCounter + 1);
        $dateStr = !empty($dateFormat) ? $date->format($dateFormat) : '';
        $smartPrefix = !empty($prefix) ? ($prefix . '-') : '';

        return ($smartPrefix . $dateStr . $currentCounterStr);
    }
}
