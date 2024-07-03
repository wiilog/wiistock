<?php
// At every minute
// * * * * *

namespace App\Command\Cron;

use App\Entity\ScheduledTask\Export;
use App\Service\ScheduledExportService;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(
    name: ScheduledExportCommand::COMMAND_NAME,
    description: 'This command executes scheduled export.'
)]
class ScheduledExportCommand extends Command
{
    public const COMMAND_NAME = 'app:launch:scheduled-exports';

    #[Required]
    public EntityManagerInterface $em;

    #[Required]
    public ScheduledExportService $exportService;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $exportRepository = $this->getEntityManager()->getRepository(Export::class);

        $exportsCache = $this->exportService->getScheduledCache($this->getEntityManager());
        $currentKeyExport = $this->exportService->getScheduleExportKeyCache(new DateTime());

        if (!empty($exportsCache[$currentKeyExport])) {
            $exports = $exportRepository->findBy(["id" => $exportsCache[$currentKeyExport]]);

            foreach ($exports as $export) {
                $this->exportService->export($this->getEntityManager(), $export);
            }

            $this->exportService->saveScheduledExportsCache($this->getEntityManager());
        }

        return 0;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return $this->em->isOpen()
            ? $this->em
            : new EntityManager($this->em->getConnection(), $this->em->getConfiguration());
    }

}
