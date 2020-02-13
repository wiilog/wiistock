<?php


namespace App\Service;


use App\Entity\ParametrageGlobal;
use App\Repository\ArrivageRepository;
use App\Repository\ArrivalHistoryRepository;
use App\Repository\EmplacementRepository;
use App\Repository\ParametrageGlobalRepository;
use App\Repository\ReceptionTracaRepository;
use App\Repository\UrgenceRepository;

class DashboardService
{

    /**
     * @var ReceptionTracaRepository
     */
    private $receptionTracaRepository;

    /**
     * @var ArrivageRepository;
     */
    private $arrivageRepository;

    /**
     * @var ArrivalHistoryRepository
     */
    private $arrivalHistoryRepository;

	/**
	 * @var ParametrageGlobalRepository
	 */
    private $parametrageGlobalRepository;

	/**
	 * @var EmplacementRepository
	 */
    private $emplacementRepository;
	/**
	 * @var EnCoursService
	 */
    private $enCoursService;
	/**
	 * @var UrgenceRepository;
	 */
    private $urgenceRepository;

    public function __construct(EmplacementRepository $emplacementRepository,
								ParametrageGlobalRepository $parametrageGlobalRepository,
								ArrivalHistoryRepository $arrivalHistoryRepository,
								ArrivageRepository $arrivageRepository,
								ReceptionTracaRepository $receptionTracaRepository,
								EnCoursService $enCoursService,
								UrgenceRepository $urgenceRepository
	)
    {
        $this->arrivalHistoryRepository = $arrivalHistoryRepository;
        $this->arrivageRepository = $arrivageRepository;
        $this->receptionTracaRepository = $receptionTracaRepository;
        $this->parametrageGlobalRepository = $parametrageGlobalRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->enCoursService = $enCoursService;
        $this->urgenceRepository = $urgenceRepository;
    }

    private $columnsForArrival = [
        [
            'type' => 'string',
            'value' => 'Jours'
        ],
        [
            'type' => 'number',
            'value' => 'Nombre d\'arrivages'
        ],
        [
            'type' => 'number',
            'value' => 'Taux d\'arrivages conformes'
        ],
        [
            'annotation' => true,
            'type' => 'string',
            'role' => 'tooltip'
        ]
    ];

    private $columnsForAssoc = [
        [
            'type' => 'string',
            'value' => 'Jours'
        ],
        [
            'type' => 'number',
            'value' => 'Réceptions'
        ],
    ];

    public function getWeekAssoc($firstDay, $lastDay, $beforeAfter) {
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
        $secondInADay = 60*60*24;

        for ($dayIncrement = 0; $dayIncrement < 7; $dayIncrement++) {
            $dayCounterKey = date("d", $firstDayTime + ($secondInADay * $dayIncrement));
            $rows[$dayCounterKey] = 0;
        }

        foreach ($this->receptionTracaRepository->countByDays($firstDay, $lastDay) as $qttPerDay) {
            $dayCounterKey = $qttPerDay['date']->format('d');
            $rows[$dayCounterKey] += $qttPerDay['count'];
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
            $rows[$dayCounterKey] = [
                'count' => 0,
                'conform' => null
            ];
        }

        foreach ($this->arrivageRepository->countByDays($firstDay, $lastDay) as $qttPerDay) {

            $dayCounterKey = $qttPerDay['date']->format('d');
            if (!isset($rows[$dayCounterKey])) {
                $rows[$dayCounterKey] = ['count' => 0];
            }

            $rows[$dayCounterKey]['count'] += $qttPerDay['count'];

            $dateHistory = $qttPerDay['date']->setTime(0, 0);
            $rows[$dayCounterKey]['conform'] =
                $this->arrivalHistoryRepository->getByDate($dateHistory)
                    ? $this->arrivalHistoryRepository->getByDate($dateHistory)->getConformRate()
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

    public function getDataForReceptionDashboard()
	{
		$empIdForDock =
			$this->parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::DASHBOARD_LOCATION_DOCK)
				?
				$this->parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::DASHBOARD_LOCATION_DOCK)->getValue()
				:
				null;
		$empIdForClearance =
			$this->parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::DASHBOARD_LOCATION_WAITING_CLEARANCE_DOCK)
				?
				$this->parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::DASHBOARD_LOCATION_WAITING_CLEARANCE_DOCK)->getValue()
				:
				null;
		$empIdForCleared =
			$this->parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::DASHBOARD_LOCATION_AVAILABLE)
				?
				$this->parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::DASHBOARD_LOCATION_AVAILABLE)->getValue()
				:
				null;
		$empIdForDropZone =
			$this->parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::DASHBOARD_LOCATION_TO_DROP_ZONES)
				?
				$this->parametrageGlobalRepository->findOneByLabel(ParametrageGlobal::DASHBOARD_LOCATION_TO_DROP_ZONES)->getValue()
				:
				null;
		$empForDock = $empIdForDock ? $this->emplacementRepository->find($empIdForDock) : null;
		$empForClearance = $empIdForClearance ? $this->emplacementRepository->find($empIdForClearance) : null;
		$empForCleared = $empIdForCleared ? $this->emplacementRepository->find($empIdForCleared) : null;
		$empForDropZone = $empIdForDropZone ? $this->emplacementRepository->find($empIdForDropZone) : null;
		return [
			'enCoursDock' => $empForDock ? [
				'count' => count($this->enCoursService->getEnCoursForEmplacement($empForDock)['data']),
				'label' => $empForDock->getLabel()
			] : null,
			'enCoursClearance' => $empForClearance ? [
				'count' => count($this->enCoursService->getEnCoursForEmplacement($empForClearance)['data']),
				'label' => $empForClearance->getLabel()
			] : null,
			'enCoursCleared' => $empForCleared ? [
				'count' => count($this->enCoursService->getEnCoursForEmplacement($empForCleared)['data']),
				'label' => $empForCleared->getLabel()
			] : null,
			'enCoursDropzone' => $empForDropZone ? [
				'count' => count($this->enCoursService->getEnCoursForEmplacement($empForDropZone)['data']),
				'label' => $empForDropZone->getLabel()
			] : null,
			'urgenceCount' => $this->urgenceRepository->countUnsolved(),
		];
	}

}
