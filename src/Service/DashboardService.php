<?php


namespace App\Service;


use App\Repository\ArrivageRepository;
use App\Repository\ArrivalHistoryRepository;
use App\Repository\ReceptionTracaRepository;

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

    public function __construct(ArrivalHistoryRepository $arrivalHistoryRepository, ArrivageRepository $arrivageRepository, ReceptionTracaRepository $receptionTracaRepository)
    {
        $this->arrivalHistoryRepository = $arrivalHistoryRepository;
        $this->arrivageRepository = $arrivageRepository;
        $this->receptionTracaRepository = $receptionTracaRepository;
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

    public function getWeekAssoc($firstDay, $lastDay, $after) {
        if ($after !== 'now') {
            if ($after) {
                $firstDay = date("d/m/Y", strtotime(str_replace("/", "-", $firstDay) . ' +7 days'));
                $lastDay = date("d/m/Y", strtotime(str_replace("/", "-", $lastDay) . ' +7 days'));
            } else {
                $firstDay = date("d/m/Y", strtotime(str_replace("/", "-", $firstDay) . ' -7 days'));
                $lastDay = date("d/m/Y", strtotime(str_replace("/", "-", $lastDay) . ' -7 days'));
            }
        }
        $firstDayTime = strtotime(str_replace("/", "-", $firstDay));
        $lastDayTime = strtotime(str_replace("/", "-", $lastDay));

        $rows = [];
        $secondInADay = 60*60*24;

        for ($dayIncrement = 0; $dayIncrement < 7; $dayIncrement++) {
            $dayCounterKey = date("d", $firstDayTime + ($secondInADay * $dayIncrement));
            $rows[$dayCounterKey] = ['count' => 0];
        }

        foreach ($this->receptionTracaRepository->countByDays($firstDay, $lastDay) as $qttPerDay) {
            $dayCounterKey = $qttPerDay['date']->format('d');
            $rows[$dayCounterKey]['count'] += $qttPerDay['count'];
        }
        return [
            'columns' => $this->columnsForAssoc,
            'rows' => $rows,
            'firstDay' => date("d/m/y", $firstDayTime),
            'firstDayData' => date("d/m/Y", $firstDayTime),
            'lastDay' => date("d/m/y", $lastDayTime),
            'lastDayData' => date("d/m/Y", $lastDayTime)
        ];
    }

    public function getWeekArrival($firstDay, $lastDay, $after)
    {
        if ($after !== 'now') {
            if ($after) {
                $firstDay = date("d/m/Y", strtotime(str_replace("/", "-", $firstDay) . ' +7 days'));
                $lastDay = date("d/m/Y", strtotime(str_replace("/", "-", $lastDay) . ' +7 days'));
            } else {
                $firstDay = date("d/m/Y", strtotime(str_replace("/", "-", $firstDay) . ' -7 days'));
                $lastDay = date("d/m/Y", strtotime(str_replace("/", "-", $lastDay) . ' -7 days'));
            }
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
            'columns' => $this->columnsForArrival,
            'rows' => $rows,
            'firstDay' => date("d/m/y", $firstDayTime),
            'firstDayData' => date("d/m/Y", $firstDayTime),
            'lastDay' => date("d/m/y", $lastDayTime),
            'lastDayData' => date("d/m/Y", $lastDayTime)
        ];
    }

}
