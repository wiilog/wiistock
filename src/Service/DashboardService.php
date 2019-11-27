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
            'annotation' => true,
            'type' => 'string',
            'role' => 'annotation'
        ],
        [
            'type' => 'number',
            'value' => 'Nombre d\'arrivages conformes'
        ],
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
        [
            'annotation' => true,
            'type' => 'string',
            'role' => 'annotation'
        ]
    ];

    public function getWeekAssoc($firstDay, $lastDay, $after)
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
        $rows = [];
        $rows[$firstDay] = [];
        $rows[$firstDay]['count'] = 0;
        foreach (['1', '2', '3', '4', '5'] as $dayIncrement) {
            $rows[date("d/m/Y", strtotime(str_replace("/", "-", $firstDay) . ' + ' . $dayIncrement . ' days'))] = [];
            $rows[date("d/m/Y", strtotime(str_replace("/", "-", $firstDay) . ' + ' . $dayIncrement . ' days'))]['count'] = 0;
        }
        $rows[$lastDay] = [];
        $rows[$lastDay]['count'] = 0;
        foreach ($this->receptionTracaRepository->countByDays($firstDay, $lastDay) as $qttPerDay) {
            $rows[$qttPerDay['date']->format('d/m/Y')]['count'] += $qttPerDay['count'];
        }
        return [
            'columns' => $this->columnsForAssoc,
            'rows' => $rows,
            'firstDay' => $firstDay,
            'lastDay' => $lastDay
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
        $rows = [];
        $rows[$firstDay] = [];
        $rows[$firstDay]['count'] = 0;
        $rows[$firstDay]['conform'] = 0;
        foreach (['1', '2', '3', '4', '5'] as $dayIncrement) {
            $rows[date("d/m/Y", strtotime(str_replace("/", "-", $firstDay) . ' + ' . $dayIncrement . ' days'))] = [];
            $rows[date("d/m/Y", strtotime(str_replace("/", "-", $firstDay) . ' + ' . $dayIncrement . ' days'))]['count'] = 0;
            $rows[date("d/m/Y", strtotime(str_replace("/", "-", $firstDay) . ' + ' . $dayIncrement . ' days'))]['conform'] = 0;
        }
        $rows[$lastDay] = [];
        $rows[$lastDay]['count'] = 0;
        $rows[$lastDay]['conform'] = 0;
        foreach ($this->arrivageRepository->countByDays($firstDay, $lastDay) as $qttPerDay) {
            $rows[$qttPerDay['date']->format('d/m/Y')]['count'] += $qttPerDay['count'];
            $dateHistory = $qttPerDay['date']->setTime(0, 0);
            $rows[$qttPerDay['date']->format('d/m/Y')]['conform'] =
                round(
                    ($this->arrivalHistoryRepository->getByDate($dateHistory)->getConformRate()/100) *
                    ($this->arrivalHistoryRepository->getByDate($dateHistory)->getNumberOfArrivals())
                );
        }
        return [
            'columns' => $this->columnsForArrival,
            'rows' => $rows,
            'firstDay' => $firstDay,
            'lastDay' => $lastDay
        ];
    }

}