<?php


namespace App\Service;


use App\Entity\DaysWorked;
use App\Entity\Emplacement;
use App\Repository\ColisRepository;
use App\Repository\DaysWorkedRepository;
use App\Repository\EmplacementRepository;
use App\Repository\MouvementTracaRepository;
use DateInterval;
use DatePeriod;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\NonUniqueResultException;


class EnCoursService
{
    /**
     * @var MouvementTracaRepository
     */
    private $mouvementTracaRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var DaysWorkedRepository
     */
    private $daysRepository;

    /**
     * @var ColisRepository
     */
    private $colisRepository;

    private const AFTERNOON_FIRST_HOUR_INDEX = 4;
    private const AFTERNOON_LAST_HOUR_INDEX = 6;
    private const AFTERNOON_FIRST_MINUTE_INDEX = 5;
    private const AFTERNOON_LAST_MINUTE_INDEX = 7;
    private const MORNING_FIRST_HOUR_INDEX = 0;
    private const MORNING_LAST_HOUR_INDEX = 2;
    private const MORNING_FIRST_MINUTE_INDEX = 1;
    private const MORNING_LAST_MINUTE_INDEX = 3;

    /**
     * EnCoursService constructor.
     * @param ColisRepository $colisRepository
     * @param MouvementTracaRepository $mouvementTracaRepository
     * @param EmplacementRepository $emplacementRepository
     * @param DaysWorkedRepository $daysRepository
     */
    public function __construct(
        ColisRepository $colisRepository,
        MouvementTracaRepository $mouvementTracaRepository,
        EmplacementRepository $emplacementRepository,
        DaysWorkedRepository $daysRepository)
    {
        $this->colisRepository = $colisRepository;
        $this->mouvementTracaRepository = $mouvementTracaRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->daysRepository = $daysRepository;
    }

    /**
     * @param DaysWorked $daysWorked the day to get the times on
     * @return array containing each minutes and hour for each points on the day
     * ex : 8:00-12:00;14:00-18:00 => [8, 0, 12, 0, 14, 0, 18, 0]
     */
    public function getTimeArrayForDayWorked(DaysWorked $daysWorked): array
    {
        $daysPeriod = explode(';', $daysWorked->getTimes());

        if (empty($daysPeriod[1])) return [];

        $afternoon = $daysPeriod[1];
        $morning = $daysPeriod[0];

        $afternoonLastHourAndMinute = explode('-', $afternoon)[1];
        $afternoonFirstHourAndMinute = explode('-', $afternoon)[0];
        $morningLastHourAndMinute = explode('-', $morning)[1];
        $morningFirstHourAndMinute = explode('-', $morning)[0];

        $afternoonLastHour = intval(explode(':', $afternoonLastHourAndMinute)[0]);
        $afternoonLastMinute = intval(explode(':', $afternoonLastHourAndMinute)[1]);
        $afternoonFirstHour = intval(explode(':', $afternoonFirstHourAndMinute)[0]);
        $afternoonFirstMinute = intval(explode(':', $afternoonFirstHourAndMinute)[1]);

        $morningLastHour = intval(explode(':', $morningLastHourAndMinute)[0]);
        $morningLastMinute = intval(explode(':', $morningLastHourAndMinute)[1]);
        $morningFirstHour = intval(explode(':', $morningFirstHourAndMinute)[0]);
        $morningFirstMinute = intval(explode(':', $morningFirstHourAndMinute)[1]);

        return [
            $morningFirstHour, $morningFirstMinute, $morningLastHour, $morningLastMinute,
            $afternoonFirstHour, $afternoonFirstMinute, $afternoonLastHour, $afternoonLastMinute
        ];
    }

    /**
     * @param DaysWorked $daysWorked the day to get total time on
     * @return int the total day time including the break;
     */
    public function getTotalTimeInDay(DaysWorked $daysWorked): int
    {
        $timeArray = $this->getTimeArrayForDayWorked($daysWorked);
        return (empty($timeArray) ? 0 : (
                ($timeArray[self::AFTERNOON_LAST_HOUR_INDEX] * 60) + $timeArray[self::AFTERNOON_LAST_MINUTE_INDEX])
            -
            (($timeArray[self::MORNING_FIRST_HOUR_INDEX] * 60) + $timeArray[self::MORNING_FIRST_MINUTE_INDEX]));
    }

    /**
     * @param DaysWorked $daysWorked
     * @return int the total day worked time, which is the total time minus the break time
     */
    public function getTimeWorkedDuringThisDayForDayWorked(DaysWorked $daysWorked): int
    {
        return $this->getTotalTimeInDay($daysWorked) - $this->getTimeBreakThisDayForDayWorked($daysWorked);
    }

    /**
     * @param DaysWorked $daysWorked the day worked
     * @return int the total break time for the param
     */
    public function getTimeBreakThisDayForDayWorked(DaysWorked $daysWorked): int
    {
        $timeArray = $this->getTimeArrayForDayWorked($daysWorked);
        return (empty($timeArray) ? 0 : (
                ($timeArray[self::AFTERNOON_FIRST_HOUR_INDEX] * 60) + $timeArray[self::AFTERNOON_FIRST_MINUTE_INDEX])
            -
            (($timeArray[self::MORNING_LAST_HOUR_INDEX] * 60) + $timeArray[self::MORNING_LAST_MINUTE_INDEX]));
    }

    /**
     * @param DateTime $day the day tested
     * @param DateTime $now now time
     * @param DateTimeInterface $dateMvt mvt's time
     * @return int the worked hours for the day tested
     * @throws NonUniqueResultException
     */
    public function getMinutesWorkedDuringThisDay(DateTime $day, DateTime $now, DateTimeInterface $dateMvt): int
    {
        /**
         * @var $dayWorked DaysWorked|null the DaysWorked entity corresponding to the day being tested
         * @var $isToday bool if the tested day is today
         * @var $isMvtDay bool if the tested day is the mvt's day
         * @var $minutesWorked int the minutes worked the tested day
         * @var $diffMinutesBetweenMvtAndNow int the difference in minutes between the mvt and now
         * @var $dayTestedTimeInMinutes int the day tested time in minutes
         * @var $nowTimeInMinutes int now time in minutes
         * @var $dateMvtTimeInMinutes int mvt's time in minutes
         */
        $dayWorked = $this->daysRepository->findOneByDayAndWorked(strtolower($day->format('l')));
        $isToday = ($day->format('Ymd') === $now->format('Ymd'));
        $isMvtDay = ($day->format('Ymd') === $dateMvt->format('Ymd'));
        $minutesWorked = 0;
        $diffMinutesBetweenMvtAndNow = ((intval($now->diff($dateMvt)->h)) * 60) + intval($now->diff($dateMvt)->i);
        $dayTestedTimeInMinutes = ((intval($day->format('H')) * 60) + intval($day->format('i')));
        $nowTimeInMinutes = (intval($now->format('H')) * 60) + intval($now->format('i'));
        $dateMvtTimeInMinutes = (intval($dateMvt->format('H')) * 60) + intval($dateMvt->format('i'));;
        // If the day being tested is a working day (ex : Monday)
        if ($dayWorked) {
            /**
             * Get the times in minutes of each point in the day
             * @var $dayWorkedBeginOfDayInMinutes (ex : 8:00 -> 480)
             * @var $dayWorkedEndOfDayInMinutes (ex : 18:00 -> 1080)
             * @var $dayWorkedBeginOfBreakInMinutes (ex : 12:00 -> 720)
             * @var $dayWorkedEndOfBreakInMinutes (ex : 14:00 -> 840)
             **/
            $timeArray = $this->getTimeArrayForDayWorked($dayWorked);

            if (empty($timeArray)) return 0;

            $dayWorkedBeginOfDayInMinutes = (($timeArray[self::MORNING_FIRST_HOUR_INDEX]) * 60) + $timeArray[self::MORNING_FIRST_MINUTE_INDEX];
            $dayWorkedEndOfDayInMinutes = (($timeArray[self::AFTERNOON_LAST_HOUR_INDEX]) * 60) + $timeArray[self::AFTERNOON_LAST_MINUTE_INDEX];
            $dayWorkedBeginOfBreakInMinutes = (($timeArray[self::MORNING_LAST_HOUR_INDEX] * 60) + $timeArray[self::MORNING_LAST_MINUTE_INDEX]);
            $dayWorkedEndOfBreakInMinutes = (($timeArray[self::AFTERNOON_FIRST_HOUR_INDEX] * 60) + $timeArray[self::AFTERNOON_FIRST_MINUTE_INDEX]);

            // If the day being tested is today
            if ($isToday) {
                /**
                 * If the day being tested is also the movement's day,
                 * which means that this day is the first and last to get tested
                 **/
                if ($isMvtDay) {
                    // We add the minutes between now and the movement's time
                    $minutesWorked += $diffMinutesBetweenMvtAndNow;
                    /**
                     * If we counted the break in the diff, we need to exclude it
                     **/
                    // Example : if mvt's time is 9:00
                    if ($dayTestedTimeInMinutes <= $dayWorkedBeginOfBreakInMinutes) {
                        // And now time is 15:00
                        if ($nowTimeInMinutes >= $dayWorkedEndOfBreakInMinutes) {
                            // We need to exclude the whole break
                            $minutesWorked -= $this->getTimeBreakThisDayForDayWorked($dayWorked);
                        } // And now time is 13:00
                        else if ($nowTimeInMinutes < $dayWorkedEndOfBreakInMinutes
                            && $nowTimeInMinutes > $dayWorkedBeginOfBreakInMinutes) {
                            // We need to exclude the difference between 12:00 and 13:00
                            $minutesWorked -= ($nowTimeInMinutes - $dayWorkedBeginOfBreakInMinutes);
                        }
                    } // Example : if the mvt's time is 13:00
                    else if ($dayTestedTimeInMinutes <= $dayWorkedEndOfBreakInMinutes) {
                        // And now time is 15:00
                        if ($nowTimeInMinutes >= $dayWorkedEndOfBreakInMinutes) {
                            // We need to exclude the difference between 14:00 and 13:00
                            $minutesWorked -= ($dayWorkedEndOfBreakInMinutes - $dayTestedTimeInMinutes);
                        } // And now time is 13:30
                        else if ($nowTimeInMinutes < $dayWorkedEndOfBreakInMinutes
                            && $nowTimeInMinutes > $dayWorkedBeginOfBreakInMinutes) {
                            // We need to exclude the whole difference that we counted because it was in the day break
                            $minutesWorked -= $diffMinutesBetweenMvtAndNow;
                        }
                    }
                } // If the day tested is not the mvt's day
                else {
                    // If now time is before the break (ex : 10:30)
                    if ($nowTimeInMinutes < $dayWorkedBeginOfBreakInMinutes) {
                        // We only count the beginning of the day to now
                        $minutesWorked += ($nowTimeInMinutes - $dayWorkedBeginOfDayInMinutes);
                    } // If now time is in the break time
                    else if ($nowTimeInMinutes < $dayWorkedEndOfBreakInMinutes) {
                        // We only count the beginning of the day to the beginning of the break
                        $minutesWorked += ($dayWorkedBeginOfBreakInMinutes - $dayWorkedBeginOfDayInMinutes);
                    } else {
                        // If now time is after the break then we can count the beginning of the day to now
                        // excluding the break time
                        $minutesWorked += (
                            ($nowTimeInMinutes - $dayWorkedBeginOfDayInMinutes)
                            -
                            $this->getTimeBreakThisDayForDayWorked($dayWorked)
                        );
                    }
                }
            }
            // If we are testing the day of the mvt but now today,
            // which means that this is the first but not the last day to get tested
            else if ($isMvtDay && !$isToday) {
                // We count the whole time between the mvt's time and the end of day
                $minutesWorked += (
                    $dayWorkedEndOfDayInMinutes -
                    $dateMvtTimeInMinutes
                );
                // Excluding the whole break is the mvt is before the break
                if ($dateMvtTimeInMinutes <= $dayWorkedBeginOfBreakInMinutes) {
                    $minutesWorked -= $this->getTimeBreakThisDayForDayWorked($dayWorked);
                } // Or excluding the time between the mvt and the end of the break if the mvt is in the break
                else if ($dateMvtTimeInMinutes <= $dayWorkedEndOfBreakInMinutes) {
                    $minutesWorked -= ($dayWorkedEndOfBreakInMinutes - $dateMvtTimeInMinutes);
                }
            } // If the day tested is neither the mvt's day or today, we can count the whole day, excluding the break;
            else {
                $minutesWorked += $this->getTimeWorkedDuringThisDayForDayWorked($dayWorked);
            }
        }
        return $minutesWorked;
    }

    /**
     * @param int $minutesBetween
     * @param Emplacement $emplacement
     * @return array containing :
     * late => if the ref stayed too long on the emp
     * time => the time stayed on the emp
     */
    public function buildDataForDatatable(int $minutesBetween, Emplacement $emplacement)
    {
        $maxTime = $emplacement->getDateMaxTime();
        if ($maxTime) {
            $timeHours = floor($minutesBetween / 60);
            $timeMinutes = $minutesBetween % 60;
            $time =
                (($timeHours > 0)
                    ? (($timeHours < 10 ? '0' : '') . $timeHours . ' h ')
                    : '')
                . (($timeHours === 0 && $timeMinutes < 0)
                    ? '< 1 min'
                    : ((($timeHours > 0 && $timeMinutes < 10) ? '0' : '') . $timeMinutes . ' min'));
            $maxTimeHours = intval(explode(':', $maxTime)[0]);
            $maxTimeMinutes = intval(explode(':', $maxTime)[1]);
            $late = false;
            if ($timeHours > $maxTimeHours) {
                $late = true;
            } else if (intval($timeHours) === $maxTimeHours) {
                $late = $timeMinutes > $maxTimeMinutes;
            }
            return [
                'time' => $time,
                'late' => $late,
            ];
        }
        return null;
    }

    /**
     * @param Emplacement $emplacement
     * @throws NonUniqueResultException
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Exception
     */
    public function getEnCoursForEmplacement(Emplacement $emplacement)
    {
        $success = true;
        $emplacementInfo = [];
        $mvtArray = $this->mouvementTracaRepository->findByEmplacementTo($emplacement);
        $mvtGrouped = [];

        foreach ($mvtArray as $mvt) {
            if (isset($mvtGrouped[$mvt->getColis()])
                && $mvtGrouped[$mvt->getColis()]->getDateTime() < $mvt->getDatetime()) {
                $mvtGrouped[$mvt->getColis()] = $mvt;
            } else if (!isset($mvtGrouped[$mvt->getColis()])) {
                $mvtGrouped[$mvt->getColis()] = $mvt;
            }
        }

        foreach ($mvtGrouped as $mvt) {
            if (intval($this->mouvementTracaRepository->findByEmplacementToAndArticleAndDate($emplacement, $mvt)) === 0) {
                $dateMvt = new DateTime($mvt->getDatetime()->format('d-m-Y H:i'), new \DateTimeZone("Europe/Paris"));
                $minutesBetween = $this->getMinutesBetween($dateMvt);
                if (empty($minutesBetween)) {
                    $success = false;
                } else {
                    $dataForTable = $this->buildDataForDatatable($minutesBetween, $emplacement);
                    if ($dataForTable) {
                        $emplacementInfo[] = [
                            'colis' => $mvt->getColis(),
                            'time' => $dataForTable['time'],
                            'date' => $dateMvt->format('d/m/Y H:i:s'),
                            'max' => $emplacement->getDateMaxTime(),
                            'late' => $dataForTable['late']
                        ];
                    }
                }
            }
        }

        return [
            'data' => $emplacementInfo,
            'sucess' => $success
        ];
    }

    /**
     * @param array $enCours
     * @param int $spanBegin
     * @param int $spanEnd
     * @return array
     * @throws NonUniqueResultException
     */
    public function getCountByNatureForEnCoursForTimeSpan(array $enCours, int $spanBegin, int $spanEnd, array $wantedNatures): array
    {
        $countByNature = [];
        foreach ($wantedNatures as $wantedNature) {
            $countByNature[$wantedNature->getLabel()] = 0;
        }
        foreach ($enCours as $enCour) {
            $hourOfEnCours  = strpos($enCour['time'], 'h') ? intval(substr($enCour['time'], 0, 2)) : 0;
            if (($spanBegin !== -1 && $hourOfEnCours >= $spanBegin && $hourOfEnCours < $spanEnd) || ($spanBegin === -1 && $enCour['late'])) {
                $entityColisForThisEnCour = $this->colisRepository->findOneByCode($enCour['colis']);
                if ($entityColisForThisEnCour) {
                    $key = $entityColisForThisEnCour->getNature()->getLabel();
                    if (array_key_exists($key, $countByNature)) {
                        $countByNature[$key] ++;
                    }
                }
            }
        }
        return $countByNature;
    }

    /**
     * @param $dateMvt
     * @return int
     * @throws \Exception
     */
    public function getMinutesBetween($dateMvt): int
    {
        $now = new DateTime("now", new \DateTimeZone("Europe/Paris"));
        $nowIncluding = (new DateTime("now", new \DateTimeZone("Europe/Paris")))
            ->add(new DateInterval('PT' . (18 - intval($now->format('H'))) . 'H'));

        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod($dateMvt, $interval, $nowIncluding);
        $minutesBetween = 0;
        /**
         * @var $day DateTime
         */
        foreach ($period as $day) {
            $minutesBetween += $this->getMinutesWorkedDuringThisDay($day, $now, $dateMvt);
        }

        return $minutesBetween;
    }
}
