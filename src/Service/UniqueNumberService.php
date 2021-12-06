<?php

namespace App\Service;

use App\Entity\Reception;
use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Entity;
use Exception;

class UniqueNumberService
{
    const MAX_RETRY = 5;
    const DATE_COUNTER_FORMAT_DEFAULT = 'YmdCCCC';
    const DATE_COUNTER_FORMAT_RECEPTION = 'ymdCCCC';

    const ENTITIES_NUMBER_WITHOUT_DASH = [
        Reception::class
    ];

    /** @Required */
    public EntityManagerInterface $entityManager;

    /**
     * getLastNumberByPrefixAndDate() function must be implemented in current entity repository with $prefix and $date params
     * @param EntityManagerInterface $entityManager
     * @param string $prefix - Prefix of the entity unique number => Available in chosen entity
     * @param string $format - Format of the entity unique number => Available in UniqueNumberService
     * @param string $entity - Chosen entity to generate unique number => Format Entity::class
     * @return string
     * @throws Exception
     */
    public function create(EntityManagerInterface $entityManager,
                           string                 $prefix,
                           string                 $entity,
                           string                 $format): string {

        $date = new DateTime('now');
        $entityRepository = $entityManager->getRepository($entity);

        if (!method_exists($entityRepository, 'getLastNumberByDate')) {
            throw new Exception("Undefined getLastNumberByDate for $entity " . "repository");
        }

        preg_match('/([^C]*)(C+)/', $format, $matches);
        if (empty($matches)) {
            throw new Exception('Invalid number format');
        }

        $dateFormat = $matches[1];
        $counterFormat = $matches[2];
        $counterLen = strlen($counterFormat);

        $dateStr = $date->format(substr($format, 0, -1 * $counterLen));
        $lastNumber = $entityRepository->getLastNumberByDate($dateStr, $prefix);

        $lastCounter = (
            (!empty($lastNumber) && $counterLen <= strlen($lastNumber))
                ? (int) substr($lastNumber, -$counterLen, $counterLen)
                : 0
        );
        $currentCounterStr = sprintf("%0{$counterLen}u", $lastCounter + 1);
        $dateStr = !empty($dateFormat) ? $date->format($dateFormat) : '';
        $smartPrefix = !empty($prefix) ? ($prefix . (!in_array($entity, self::ENTITIES_NUMBER_WITHOUT_DASH) ? '-' : '')) : '';

        return ($smartPrefix . $dateStr . $currentCounterStr);
    }

    public function createWithRetry(EntityManagerInterface $entityManager,
                                    string                 $prefix,
                                    string                 $entity,
                                    string                 $format,
                                    callable               $flush,
                                    int                    $maxNbRetry = self::MAX_RETRY): void {

        $nbTry = 0;
        do {
            try {
                $number = $this->create($entityManager, $prefix, $entity, $format);

                $nbTry++;
                $flush($number);
                $stopTrying = true;
            } catch (UniqueConstraintViolationException $e) {
                $stopTrying = ($nbTry >= $maxNbRetry);
            }
        }
        while (!$stopTrying);
    }

}
