<?php

namespace App\Service;

use App\Entity\ProductionRequest;
use App\Entity\Reception;
use App\Entity\Transport\TransportRequest;
use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Entity;
use Exception;
use Symfony\Contracts\Service\Attribute\Required;

class UniqueNumberService
{
    const MAX_RETRY = 5;
    const DATE_COUNTER_FORMAT_DEFAULT = 'YmdCCCC';
    const DATE_COUNTER_FORMAT_RECEPTION = 'ymdCCCC';
    const DATE_COUNTER_FORMAT_TRANSPORT = 'ymd-CC';
    const DATE_COUNTER_FORMAT_TRUCK_ARRIVAL = 'YmdHis_\{\0\}_CC';
    const DATE_COUNTER_FORMAT_DISPATCH_LONG = 'YmdHis-CCCC';
    const DATE_COUNTER_FORMAT_DISPATCH = 'ymdHisCC';
    const DATE_COUNTER_FORMAT_COLLECT = 'ymdHisCC';
    const DATE_COUNTER_FORMAT_ARRIVAL_SHORT = 'ymdHiCC';
    const DATE_COUNTER_FORMAT_ARRIVAL_LONG = 'ymdHis-CC';
    const DATE_COUNTER_FORMAT_PRODUCTION_REQUEST = 'YmdHiCCCC';

    const ENTITIES_NUMBER_WITHOUT_DASH = [
        Reception::class,
        TransportRequest::class,
    ];

    const FORMAT_NUMBER_WITHOUT_PREFIX_AND_DASH = [
        UniqueNumberService::DATE_COUNTER_FORMAT_DISPATCH,
    ];

    #[Required]
    public EntityManagerInterface $entityManager;

    private array $lastNumberByEntity = [];

    /**
     * getLastNumberByPrefixAndDate() function must be implemented in current entity repository with $prefix and $date params
     * @param EntityManagerInterface $entityManager
     * @param string|null $prefix - Prefix of the entity unique number => Available in chosen entity
     * @param string $format - Format of the entity unique number => Available in UniqueNumberService
     * @param string $entity - Chosen entity to generate unique number => Format Entity::class
     * @return string
     * @throws Exception
     */
    public function create(EntityManagerInterface $entityManager,
                           ?string                $prefix,
                           string                 $entity,
                           string                 $format,
                           ?DateTime              $numberDate = null,
                           array                  $params = []): string {
        $date = $numberDate ?? new DateTime('now');
        $entityRepository = $entityManager->getRepository($entity);

        if (!method_exists($entityRepository, 'getLastNumberByDate')) {
            throw new Exception("Undefined getLastNumberByDate for $entity " . "repository");
        }

        preg_match('/([^C]*)-?(C+)/', $format, $matches);
        if (empty($matches)) {
            throw new Exception('Invalid number format');
        }

        $dateFormat = $matches[1];
        $counterFormat = $matches[2];

        $dateStr = $date->format($dateFormat);

        $lastNumber = $this->lastNumberByEntity[$entity] ?? $entityRepository->getLastNumberByDate($dateStr, $prefix, $format);

        $counterLen = strlen($counterFormat);

        $lastCounter = (!empty($lastNumber) && $counterLen <= strlen($lastNumber))
            ? (int)substr($lastNumber, -$counterLen, $counterLen)
            : 0;

        $currentCounterStr = sprintf("%0{$counterLen}u", $lastCounter + 1);
        $dateStr = !empty($dateFormat) ? $date->format($dateFormat) : '';
        $smartPrefix = !empty($prefix) && !in_array($format, self::FORMAT_NUMBER_WITHOUT_PREFIX_AND_DASH)
            ? ($prefix . (!in_array($entity, self::ENTITIES_NUMBER_WITHOUT_DASH) ? '-' : ''))
            : '';

        foreach ($params as $key => $data) {
            $dateStr = str_replace("{" . $key . "}", $data, $dateStr);
        }

        $number = $smartPrefix . $dateStr . $currentCounterStr;

        $this->lastNumberByEntity[$entity] = $number;

        return $number;
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
        } while (!$stopTrying);
    }

}
