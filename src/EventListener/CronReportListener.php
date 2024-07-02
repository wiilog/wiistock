<?php


namespace App\EventListener;


use App\Service\ExceptionLoggerService;
use Cron\CronBundle\Entity\CronReport;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\Service\Attribute\Required;
use WiiCommon\Helper\Stream;

class CronReportListener implements EventSubscriber
{
    #[Required]
    public ExceptionLoggerService $exceptionLoggerService;

    /**
     * @var CronReport[]
     */
    private array $flushedErrorCronReports = [];

    public function getSubscribedEvents(): array {
        return [
            'onFlush',
            'postFlush',
        ];
    }

    #[AsEventListener(event: 'onFlush')]
    public function onFlush(OnFlushEventArgs $args): void {
        $this->flushedErrorCronReports = Stream::from($args->getObjectManager()->getUnitOfWork()->getScheduledEntityInsertions())
            ->filter(static fn($entity) => $entity instanceof CronReport && $entity->getExitCode() !== 0)
            ->toArray();
    }

    #[AsEventListener(event: 'postFlush')]
    public function postFlush(PostFlushEventArgs $args): void {
        foreach ($this->flushedErrorCronReports ?? [] as $cronReports) {
            $this->exceptionLoggerService->sendLog(
                new \Exception("Cron job failed: {$cronReports->getJob()->getName()}. Output: {$cronReports->getOutput()} Error: {$cronReports->getError()}"),
                null
            );
        }
    }
}
