<?php


namespace App\Service;


use App\Entity\Arrivage;
use App\Entity\ArrivalHistory;
use App\Entity\Colis;
use App\Entity\DashboardMeter;
use App\Entity\DaysWorked;
use App\Entity\Emplacement;
use App\Entity\MouvementTraca;
use App\Entity\ParametrageGlobal;
use App\Entity\ReceptionTraca;
use App\Entity\Transporteur;
use App\Entity\Urgence;
use App\Repository\DashboardMeterRepository;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMException;
use Exception;


class DashboardService
{
    private $enCoursService;
    private $entityManager;

    public function __construct(EnCoursService $enCoursService,
                                EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->enCoursService = $enCoursService;
    }

    public function getWeekAssoc($firstDay, $lastDay, $beforeAfter)
    {
        $receptionTracaRepository = $this->entityManager->getRepository(ReceptionTraca::class);

        if ($beforeAfter == 'after') {
            $firstDay = date("d/m/Y", strtotime(str_replace("/", "-", $firstDay) . ' +7 days'));
            $lastDay = date("d/m/Y", strtotime(str_replace("/", "-", $lastDay) . ' +7 days'));
        } elseif ($beforeAfter == 'before') {
            $firstDay = date("d/m/Y", strtotime(str_replace("/", "-", $firstDay) . ' -7 days'));
            $lastDay = date("d/m/Y", strtotime(str_replace("/", "-", $lastDay) . ' -7 days'));
        }
        $firstDayTime = strtotime(str_replace("/", "-", $firstDay));
        $lastDayTime = strtotime(str_replace("/", "-", $lastDay));

        $rows = [];
        $secondInADay = 60 * 60 * 24;

        for ($dayIncrement = 0; $dayIncrement < 7; $dayIncrement++) {
            $dayCounterKey = date("d", $firstDayTime + ($secondInADay * $dayIncrement));
            $rows[intval($dayCounterKey)] = 0;
        }

        $receptionTracas = $receptionTracaRepository->countByDays($firstDay, $lastDay);
        foreach ($receptionTracas as $qttPerDay) {
            $dayCounterKey = $qttPerDay['date']->format('d');
            $rows[intval($dayCounterKey)] += $qttPerDay['count'];
        }
        return [
            'data' => $rows,
            'firstDay' => date("d/m/y", $firstDayTime),
            'firstDayData' => date("d/m/Y", $firstDayTime),
            'lastDay' => date("d/m/y", $lastDayTime),
            'lastDayData' => date("d/m/Y", $lastDayTime)
        ];
    }

    public function getWeekArrival($firstDay, $lastDay, $beforeAfter)
    {
        $arrivalHistoryRepository = $this->entityManager->getRepository(ArrivalHistory::class);
        $arrivageRepository = $this->entityManager->getRepository(Arrivage::class);

        if ($beforeAfter == 'after') {
            $firstDay = date("d/m/Y", strtotime(str_replace("/", "-", $firstDay) . ' +7 days'));
            $lastDay = date("d/m/Y", strtotime(str_replace("/", "-", $lastDay) . ' +7 days'));
        } else if ($beforeAfter == 'before') {
            $firstDay = date("d/m/Y", strtotime(str_replace("/", "-", $firstDay) . ' -7 days'));
            $lastDay = date("d/m/Y", strtotime(str_replace("/", "-", $lastDay) . ' -7 days'));
        }

        $firstDayTime = strtotime(str_replace("/", "-", $firstDay));
        $lastDayTime = strtotime(str_replace("/", "-", $lastDay));

        $rows = [];
        $secondInADay = 60 * 60 * 24;

        for ($dayIncrement = 0; $dayIncrement < 7; $dayIncrement++) {
            $dayCounterKey = date("d", $firstDayTime + ($secondInADay * $dayIncrement));
            $rows[intval($dayCounterKey)] = [
                'count' => 0,
                'conform' => null
            ];
        }

        $arrivages = $arrivageRepository->countByDays($firstDay, $lastDay);
        foreach ($arrivages as $qttPerDay) {
            $dayCounterKey = intval($qttPerDay['date']->format('d'));
            if (!isset($rows[$dayCounterKey])) {
                $rows[$dayCounterKey] = ['count' => 0];
            }

            $rows[$dayCounterKey]['count'] += $qttPerDay['count'];

            $dateHistory = $qttPerDay['date']->setTime(0, 0);

            $arrivalHistory = $arrivalHistoryRepository->getByDate($dateHistory);

            $rows[$dayCounterKey]['conform'] = isset($arrivalHistory)
                ? $arrivalHistory->getConformRate()
                : null;
        }
        return [
            'data' => $rows,
            'firstDay' => date("d/m/y", $firstDayTime),
            'firstDayData' => date("d/m/Y", $firstDayTime),
            'lastDay' => date("d/m/y", $lastDayTime),
            'lastDayData' => date("d/m/Y", $lastDayTime)
        ];
    }

    /**
     * @return array
     */
    public function getDataForReceptionDockDashboard()
    {
        $locationCounter = $this->getLocationCounters([
            'enCoursDock' => ParametrageGlobal::DASHBOARD_LOCATION_DOCK,
            'enCoursClearance' => ParametrageGlobal::DASHBOARD_LOCATION_WAITING_CLEARANCE_DOCK,
            'enCoursCleared' => ParametrageGlobal::DASHBOARD_LOCATION_AVAILABLE,
            'enCoursDropzone' => ParametrageGlobal::DASHBOARD_LOCATION_TO_DROP_ZONES
        ]);

        $urgenceRepository = $this->entityManager->getRepository(Urgence::class);
        return array_merge(
            $locationCounter,
            ['dailyUrgenceCount' => $urgenceRepository->countUnsolved(true)],
            ['urgenceCount' => $urgenceRepository->countUnsolved()]
        );
    }

    /**
     * @return array
     */
    public function getDataForReceptionAdminDashboard()
    {
        $locationCounter = $this->getLocationCounters([
            'enCoursUrgence' => ParametrageGlobal::DASHBOARD_LOCATION_URGENCES,
            'enCoursLitige' => ParametrageGlobal::DASHBOARD_LOCATION_LITIGES,
            'enCoursClearance' => ParametrageGlobal::DASHBOARD_LOCATION_WAITING_CLEARANCE_ADMIN
        ]);

        $urgenceRepository = $this->entityManager->getRepository(Urgence::class);

        return array_merge(
            $locationCounter,
            ['urgenceCount' => $urgenceRepository->countUnsolved()]
        );
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getDataForMonitoringPackagingDashboard()
    {
        $defaultDelay = '24:00';
        $urgenceDelay = '2:00';
        $dsqrLabel = 'OF envoyés par le DSQR';
        $gtLabel = 'OF traités par GT';
        $counters = $this->getLocationCounters([
            'packaging1' => [ParametrageGlobal::DASHBOARD_PACKAGING_1, $defaultDelay],
            'packaging2' => [ParametrageGlobal::DASHBOARD_PACKAGING_2, $defaultDelay],
            'packaging3' => [ParametrageGlobal::DASHBOARD_PACKAGING_3, $defaultDelay],
            'packaging4' => [ParametrageGlobal::DASHBOARD_PACKAGING_4, $defaultDelay],
            'packaging5' => [ParametrageGlobal::DASHBOARD_PACKAGING_5, $defaultDelay],
            'packaging6' => [ParametrageGlobal::DASHBOARD_PACKAGING_6, $defaultDelay],
            'packaging7' => [ParametrageGlobal::DASHBOARD_PACKAGING_7, $defaultDelay],
            'packaging8' => [ParametrageGlobal::DASHBOARD_PACKAGING_8, $defaultDelay],
            'packagingRPA' => [ParametrageGlobal::DASHBOARD_PACKAGING_RPA, $defaultDelay],
            'packagingLitige' => [ParametrageGlobal::DASHBOARD_PACKAGING_LITIGE, $defaultDelay],
            'packagingUrgence' => [ParametrageGlobal::DASHBOARD_PACKAGING_URGENCE, $urgenceDelay]
        ]);

        $mouvementTracaRepository = $this->entityManager->getRepository(MouvementTraca::class);
        $parametrageGlobalRepository = $this->entityManager->getRepository(ParametrageGlobal::class);
        $locationDropIds = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DASHBOARD_PACKAGING_DSQR);
        $locationOriginIds = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DASHBOARD_PACKAGING_ORIGINE_GT);
        $locationTargetIds = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DASHBOARD_PACKAGING_DESTINATION_GT);
        $locationDropIdsArray = !empty($locationDropIds) ? explode(',', $locationDropIds) : [];
        $locationOriginIdsArray = !empty($locationOriginIds) ? explode(',', $locationOriginIds) : [];
        $locationTargetIdsArray = !empty($locationTargetIds) ? explode(',', $locationTargetIds) : [];

        $chartData = $this->getDailyObjectsStatistics(function (DateTime $dateMin, DateTime $dateMax)
                                                      use ($dsqrLabel, $gtLabel, $mouvementTracaRepository, $locationDropIdsArray, $locationOriginIdsArray, $locationTargetIdsArray) {
            return [
                $dsqrLabel => $mouvementTracaRepository->countDropsOnLocations($locationDropIdsArray, $dateMin, $dateMax),
                $gtLabel => $mouvementTracaRepository->countMovementsFromInto($locationOriginIdsArray, $locationTargetIdsArray, $dateMin, $dateMax)
            ];
        });

        return [
            'counters' => $counters,
            'chartData' => $chartData,
            'chartColors' => [
                $dsqrLabel => '#003871',
                $gtLabel => '#77933C',
            ]
        ];
    }

    /**
     * @param array $counterConfig
     * @return array
     */
    private function getLocationCounters(array $counterConfig): array
    {
        $workedDaysRepository = $this->entityManager->getRepository(DaysWorked::class);
        $daysWorked = $workedDaysRepository->getWorkedTimeForEachDaysWorked();
        return array_reduce(
            array_keys($counterConfig),
            function (array $carry, string $key) use ($counterConfig, $daysWorked) {
                $delay = is_array($counterConfig[$key])
                    ? $counterConfig[$key][1]
                    : null;
                $param = is_array($counterConfig[$key])
                    ? $counterConfig[$key][0]
                    : $counterConfig[$key];
                $carry[$key] = $this->getDashboardCounter($param, false, [], $daysWorked, $delay);
                return $carry;
            },
            []);
    }

    /**
     * @param string $paramName
     * @param bool $isPack
     * @param DateTime[]|null $onDateBracket ['minDate' => DateTime, 'maxDate' => DateTime]
     * @param array $daysWorked
     * @param string|null $delay
     * @return array|null
     * @throws Exception
     */
    public function getDashboardCounter(string $paramName,
                                        bool $isPack = false,
                                        array $onDateBracket = [],
                                        array $daysWorked = [],
                                        ?string $delay = null): ?array
    {
        $colisRepository = $this->entityManager->getRepository(Colis::class);
        $mouvementTracaRepository = $this->entityManager->getRepository(MouvementTraca::class);
        $locations = $this->findEmplacementsParam($paramName);
        if (!empty($locations)) {
            $response = [];
            $response['locations'] = $locations;
            $response['delay'] = null;
            if (!$isPack && $delay) {
                $lastEnCours = $mouvementTracaRepository->getForPacksOnLocations($locations, $onDateBracket, 'datetime', 1);
                if (!empty($lastEnCours[0])) {
                    $lastEnCoursDateTime = new DateTime($lastEnCours[0], new DateTimeZone('Europe/Paris'));
                    $date = $this->enCoursService->getTrackingMovementAge($daysWorked, $lastEnCoursDateTime);
                    $timeInformation = $this->enCoursService->getTimeInformation($date, $delay);
                    $response['delay'] = $timeInformation['countDownLateTimespan'];
                }
            }
            $response['count'] = 0;
            $response['label'] = array_reduce(
                $locations,
                function (string $carry, Emplacement $location) {
                    return $carry . (!empty($carry) ? ', ' : '') . $location->getLabel();
                },
                ''
            );
            $response['count'] = $isPack
                ? $colisRepository->countPacksOnLocations($locations, $onDateBracket)
                : count($mouvementTracaRepository->getForPacksOnLocations($locations, $onDateBracket));
        } else {
            $response = null;
        }
        return $response;
    }

    private function findEmplacementsParam(string $paramName): ?array
    {
        $emplacementRepository = $this->entityManager->getRepository(Emplacement::class);
        $parametrageGlobalRepository = $this->entityManager->getRepository(ParametrageGlobal::class);

        $param = $parametrageGlobalRepository->findOneByLabel($paramName);
        $locations = [];
        if ($param && $param->getValue()) {
            $locations = $emplacementRepository->findByIds(explode(',', $param->getValue()));
        }
        return $locations;
    }

    /**
     * Make assoc array. Assoc a date like "d/m" to a counter returned by given function
     * If table DaysWorked is no filled then the returned array is empty
     * Else we return an array with 7 counters
     * @param callable $getCounter (DateTime $dateMin, DateTime $dateMax) => integer
     * @return array ['d/m' => integer]
     * @throws Exception
     */
    public function getDailyObjectsStatistics(callable $getCounter): array
    {
        $daysWorkedRepository = $this->entityManager->getRepository(DaysWorked::class);

        $daysToReturn = [];
        $nbDaysToReturn = 7;
        $dayIndex = 0;

        $workedDaysLabels = $daysWorkedRepository->getLabelWorkedDays();

        if (!empty($workedDaysLabels)) {
            while (count($daysToReturn) < $nbDaysToReturn) {
                $dateToCheck = new DateTime("now - $dayIndex days", new DateTimeZone('Europe/Paris'));
                $dateDayLabel = strtolower($dateToCheck->format('l'));

                if (in_array($dateDayLabel, $workedDaysLabels)) {
                    $daysToReturn[] = $dateToCheck;
                }

                $dayIndex++;
            }
        }

        return array_reduce(
            array_reverse($daysToReturn),
            function (array $carry, DateTime $dateToCheck) use ($getCounter) {
                $dateMin = clone $dateToCheck;
                $dateMin->setTime(0, 0, 0);
                $dateMax = clone $dateToCheck;
                $dateMax->setTime(23, 59, 59);
                $dateToCheck->setTime(0, 0);

                $dayKey = $dateToCheck->format('d/m');
                $carry[$dayKey] = $getCounter($dateMin, $dateMax);
                return $carry;
            },
            []);
    }

    /**
     * Make assoc array. Assoc a date like ('S' . weekNumber) to a counter returned by given function
     * If table DaysWorked is no filled then the returned array is empty
     * Else we return an array with 5 counters
     * @param callable $getCounter (DateTime $dateMin, DateTime $dateMax) => integer
     * @return array [('S' . weekNumber) => integer]
     * @throws Exception
     */
    public function getWeeklyObjectsStatistics(callable $getCounter): array
    {
        $daysWorkedRepository = $this->entityManager->getRepository(DaysWorked::class);

        $weekCountersToReturn = [];
        $nbWeeksToReturn = 5;

        $daysWorkedInWeek = $daysWorkedRepository->countDaysWorked();

        if ($daysWorkedInWeek > 0) {
            for ($weekIndex = ($nbWeeksToReturn - 2); $weekIndex >= -1; $weekIndex--) {
                $dateMin = new DateTime("monday $weekIndex weeks ago");
                $dateMin->setTime(0, 0, 0);
                $dateMax = new DateTime("sunday $weekIndex weeks ago");
                $dateMax->setTime(23, 59, 59);
                $dayKey = ('S' . $dateMin->format('W'));
                $weekCountersToReturn[$dayKey] = $getCounter($dateMin, $dateMax);
            }
        }

        return $weekCountersToReturn;
    }

    /**
     * @param callable $getObject
     * @return array
     */
    public function getObjectForTimeSpan(callable $getObject): array
    {
        $timeSpanToObject = [];
        $timeSpans = [
            -1 => -1,
            0 => 1,
            1 => 6,
            6 => 12,
            12 => 24,
            24 => 36,
            36 => 48,
        ];
        foreach ($timeSpans as $timeBegin => $timeEnd) {
            $key = $timeBegin === -1
                ? "Retard"
                : ($timeEnd === 1
                    ? "Moins d'1h"
                    : ($timeBegin . "h-" . $timeEnd . 'h'));
            $timeSpanToObject[$key] = $getObject($timeBegin, $timeEnd);
        }
        return $timeSpanToObject;
    }

    /**
     * @return array
     */
    public function getDailyArrivalCarriers(): array
    {
        $transporteurRepository = $this->entityManager->getRepository(Transporteur::class);
        $parametrageGlobalRepository = $this->entityManager->getRepository(ParametrageGlobal::class);
        $carriersParams = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::DASHBOARD_CARRIER_DOCK);
        $carriersIds = empty($carriersParams)
            ? []
            : explode(',', $carriersParams);

        return array_map(
            function ($carrier) {
                return $carrier['label'];
            },
            $transporteurRepository->getDailyArrivalCarriersLabel($carriersIds)
        );
    }


    /**
     * @param EntityManagerInterface $entityManager
     * @throws Exception
     */
    public function retrieveAndInsertGlobalDashboardData(EntityManagerInterface $entityManager) {
        $dashboardMeterRepository = $entityManager->getRepository(DashboardMeter::class);
        $dashboardMeterRepository->clearTable();
        $this->retrieveAndInsertParsedDockData($entityManager);
        $this->retrieveAndInsertParsedAdminData($entityManager);
        $this->retrieveAndInsertParsedPackagingData($entityManager);
    }

    /**
     * @param EntityManagerInterface $entityManager
     */
    private function retrieveAndInsertParsedDockData(EntityManagerInterface $entityManager) : void {
        $dockData = $this->getDataForReceptionDockDashboard();
        $this->parseRetrievedDataAndPersistMeter($dockData, DashboardMeter::DASHBOARD_DOCK, $entityManager);
    }

    /**
     * @param $data
     * @param string $dashboard
     * @param EntityManagerInterface $entityManager
     */
    private function parseRetrievedDataAndPersistMeter($data, string $dashboard, EntityManagerInterface $entityManager): void {
        foreach ($data as $key => $datum) {
            $dashboardMeter = new DashboardMeter();
            $dashboardMeter->setMeterKey($key);
            $dashboardMeter->setDashboard($dashboard);
            if (is_array($datum)) {
                $dashboardMeter
                    ->setCount($datum['count'])
                    ->setDelay($datum['delay'])
                    ->setLabel($datum['label']);
                $entityManager->persist($dashboardMeter);
            } else {
                $dashboardMeter->setCount(intval($datum));
                $entityManager->persist($dashboardMeter);
            }
        }
    }

    /**
     * @param EntityManagerInterface $entityManager
     */
    private function retrieveAndInsertParsedAdminData(EntityManagerInterface $entityManager): void {
        $adminData = $this->getDataForReceptionAdminDashboard();
        $this->parseRetrievedDataAndPersistMeter($adminData, DashboardMeter::DASHBOARD_ADMIN, $entityManager);
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @throws Exception
     */
    private function retrieveAndInsertParsedPackagingData(EntityManagerInterface $entityManager): void {
        $packagingData = $this->getDataForMonitoringPackagingDashboard();
        $this->parseRetrievedDataAndPersistMeter($packagingData['counters'], DashboardMeter::DASHBOARD_PACKAGING, $entityManager);
    }


}
