<?php

namespace App\Command\DataArchiving\arrivalArchiving;

use App\Entity\Arrivage;
use App\Helper\FileSystem;
use App\Repository\ArrivageRepository;
use App\Service\ArrivageService;
use App\Service\CSVExportService;
use App\Service\DataExportService;
use App\Service\FormatService;
use App\Service\FreeFieldService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Serializer\SerializerInterface;
use WiiCommon\Helper\Stream;

#[AsCommand(
    name: ArrivalBatchArchivingCommand::COMMAND_NAME,
    description: 'Archives arrivals in batches of 1000 until memory usage reaches 75% of the limit or no more arrivals are found.'
)]
class ArrivalBatchArchivingCommand extends Command
{
    const COMMAND_NAME = 'app:purge:batch-arrival';
    const ARCHIVE_ARRIVALS_OLDER_THAN = 2;
    public const DATA_ARCHIVING_THRESHOLD = "years";
    const TEMPORARY_DIR = '/var/dataArchiving/';
    const BATCH_SIZE = 1000;
    const MEMORY_LIMIT = 2147483648; // 2GB
    const MEMORY_USAGE_THRESHOLD = 0.75;

    private FileSystem $filesystem;
    private string $absoluteCachePath;


    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CSVExportService $csvExportService,
        private readonly FreeFieldService $freeFieldService,
        private readonly SerializerInterface $serializer,
        private readonly FormatService $formatService,
        private readonly DataExportService $dataExportService,
        private readonly ArrivageService $arrivageService,
        KernelInterface $kernel
    ) {
        parent::__construct();
        $this->absoluteCachePath = $kernel->getProjectDir() . self::TEMPORARY_DIR;
        $this->filesystem = new FileSystem($this->absoluteCachePath);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Archiving arrivals in batches of ' . self::BATCH_SIZE);

        ini_set('memory_limit', (string)self::MEMORY_LIMIT);

        $dateToArchive = new DateTime('-' . self::ARCHIVE_ARRIVALS_OLDER_THAN . self::DATA_ARCHIVING_THRESHOLD);
        $arrivageRepository = $this->entityManager->getRepository(Arrivage::class);
        $arrivageExportableColumnsSorted = $this->arrivageService->getArrivalExportableColumnsSorted($this->entityManager);

        $files = $this->initializeFiles($dateToArchive, $arrivageExportableColumnsSorted, $io);

        $this->processArrivals($arrivageRepository, $dateToArchive, $arrivageExportableColumnsSorted, $files, $io);

        $this->finalizeFiles($files);
        $io->success('Arrival archiving completed.');

        return Command::SUCCESS;
    }

    private function initializeFiles(DateTime $dateToArchive, array $arrivageExportableColumnsSorted, SymfonyStyle $io): array
    {
        $fileNames = Stream::from([
            Arrivage::class,
        ])->keyMap(fn($entityToArchive) => [
            $entityToArchive,
            $this->dataExportService->generateDataArchichingFileName(
                $this->dataExportService->getEntityName($entityToArchive),
                $dateToArchive
            ),
        ])->toArray();

        $files = [];

        foreach ($fileNames as $entityToArchive => $fileName) {
            if (!$this->filesystem->isDir()) {
                $this->filesystem->mkdir();
            }

            $fileExists = $this->filesystem->exists($fileName);
            $file = fopen($this->absoluteCachePath . $fileName, 'a');

            if (!$fileExists) {
                $io->text('Creating file ' . $fileName);
                $fileHeader = $arrivageExportableColumnsSorted["labels"];
                $this->csvExportService->putLine($file, $fileHeader);
            } else {
                $io->warning('File ' . $fileName . ' already exists. Appending data.');
            }

            $files[$entityToArchive] = $file;
        }

        return $files;
    }

    private function processArrivals(ArrivageRepository $repository, DateTime $dateToArchive, array $columnsSorted, array $files, SymfonyStyle $io): void
    {
        $totalToArchive = $repository->countOlderThan($dateToArchive);
        $io->progressStart($totalToArchive);

        $batch = [];
        $batchCount = 0;

        $to = new DateTime();

        $this->arrivageService->launchExportCache($this->entityManager, $dateToArchive, $to);
        foreach ($repository->iterateOlderThan($dateToArchive) as $arrival) {
            $this->arrivageService->putArrivalLine(
                $files[Arrivage::class],
                $arrival,
                $columnsSorted['codes']
            );

            $batch[] = $arrival;
            $batchCount++;
            $io->progressAdvance();

            if ($batchCount === self::BATCH_SIZE) {
                $this->archiveBatch($batch);
                $this->flushFiles($files);
                $batch = [];
                $batchCount = 0;

                if ($this->isMemoryLimitReached()) {
                    $io->warning('Memory limit reached. Stopping further processing.');
                    break;
                }
            }
        }

        if (!empty($batch)) {
            $this->archiveBatch($batch);
        }

        $io->progressFinish();
    }

    private function archiveBatch(array $arrivals): void
    {
        foreach ($arrivals as $arrival) {
            $this->detachArrivalRelationships($arrival);
            $this->entityManager->remove($arrival);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    private function detachArrivalRelationships(Arrivage $arrival): void
    {
        if ($reception = $arrival->getReception()) {
            $reception->setArrival(null);
        }

        foreach ($arrival->getAttachments() as $attachment) {
            $attachment->setArrivage(null);
        }

        foreach ($arrival->getUrgences() as $urgence) {
            $urgence->setLastArrival(null);
        }

        foreach ($arrival->getPacks() as $pack) {
            $pack->setArrivage(null);
        }
    }

    private function finalizeFiles(array $files): void
    {
        foreach ($files as $file) {
            fclose($file);
        }
    }

    private function flushFiles(array $files): void
    {
        foreach ($files as $file) {
            fflush($file);
        }
    }

    private function isMemoryLimitReached(): bool
    {
        return memory_get_usage() > (self::MEMORY_LIMIT * self::MEMORY_USAGE_THRESHOLD);
    }
}
