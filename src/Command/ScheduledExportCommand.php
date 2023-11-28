<?php


namespace App\Command;

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
    name: 'app:launch:scheduled-exports',
    description: 'This command executes scheduled export.'
)]
class ScheduledExportCommand extends Command
{


    #[Required]
    public EntityManagerInterface $em;

    #[Required]
    public ScheduledExportService $exportService;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $exportRepository = $this->getEntityManager()->getRepository(Export::class);

        $exportsCache = $this->exportService->getScheduledCache($this->getEntityManager());
        $currentKeyExport = $this->exportService->getScheduleExportKeyCache(new DateTime());

        if (isset($exportsCache[$currentKeyExport])) {
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
