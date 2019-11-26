<?php


namespace App\Service;


use App\Repository\ReceptionTracaRepository;

class DashboardService
{

    /**
     * @var ReceptionTracaRepository
     */
    private $receptionTracaRepository;

    public function __construct(ReceptionTracaRepository $receptionTracaRepository)
    {
        $this->receptionTracaRepository = $receptionTracaRepository;
    }


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
        $rows[$firstDay] = 0;
        $rows[date("d/m/Y", strtotime(str_replace("/", "-", $firstDay) . ' + 1 days'))] = 0;
        $rows[date("d/m/Y", strtotime(str_replace("/", "-", $firstDay) . ' + 2 days'))] = 0;
        $rows[date("d/m/Y", strtotime(str_replace("/", "-", $firstDay) . ' + 3 days'))] = 0;
        $rows[date("d/m/Y", strtotime(str_replace("/", "-", $firstDay) . ' + 4 days'))] = 0;
        $rows[date("d/m/Y", strtotime(str_replace("/", "-", $firstDay) . ' + 5 days'))] = 0;
        $rows[$lastDay] = 0;
        foreach ($this->receptionTracaRepository->countByDays($firstDay, $lastDay) as $qttPerDay) {
            $rows[$qttPerDay['date']->format('d/m/Y')] += $qttPerDay['count'];
        }
        return [
            'columns' => $this->columnsForAssoc,
            'rows' => $rows,
            'firstDay' => $firstDay,
            'lastDay' => $lastDay
        ];
    }

}