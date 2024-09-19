<?php


namespace App\EventListener;


use App\Service\ExceptionLoggerService;
use Cron\CronBundle\Entity\CronReport;


class CronReportListener
{
    public function __construct(
        private  ExceptionLoggerService $exceptionLoggerService
    ) {}

    /**
     * @var CronReport[]
     */
    private array $flushedErrorCronReports = [];

    public function postPersist(CronReport $cronReport): void {
        if ($cronReport->getExitCode() != 0) {
            $this->flushedErrorCronReports[] = $cronReport;
        }
    }

    public function postFlush(): void {
        foreach ($this->flushedErrorCronReports as $cronReports) {
            $cronException = new \Exception("Cron job failed: {$cronReports->getJob()->getName()}. Output: {$cronReports->getOutput()} Error: {$cronReports->getError()}");
            $this->exceptionLoggerService->sendLog($cronException);
        }
        $this->flushedErrorCronReports = [];
    }
}
