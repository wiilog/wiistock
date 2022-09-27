<?php


namespace App\Command;

use App\Entity\Export;
use App\Exceptions\FTPException;
use App\Service\FTPService;
use App\Service\ScheduledExportService;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

class ScheduledExportCommand extends Command
{

    private const DEFAULT_NAME = "app:launch:scheduled-exports";

    #[Required]
    public EntityManagerInterface $em;

    #[Required]
    public ScheduledExportService $exportService;

    #[Required]
    public FTPService $ftpService;

    protected function configure()
    {
        $this->setName(self::DEFAULT_NAME)
            ->setDescription("This command executes scheduled export.");
    }

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
            : EntityManager::create($this->em->getConnection(), $this->em->getConfiguration());
    }

}
