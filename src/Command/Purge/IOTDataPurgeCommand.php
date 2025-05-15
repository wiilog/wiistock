<?php

namespace App\Command\Purge;

use App\Entity\IoT\Sensor;
use App\Entity\IOT\SensorMessage;
use App\Repository\IOT\SensorMessageRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: IOTDataPurgeCommand::COMMAND_NAME,
    description: 'Archives and purges IoT messages, sensors, and actuators.'
)]
class IOTDataPurgeCommand extends Command
{
    public const COMMAND_NAME = 'app:purge:iot';
    private const SCRIPT_NAME = 'exportIOT.php';

    private const BATCH_SIZE = 1000;

    private SymfonyStyle $io;
    public function __construct(private EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->io->title('Archiving IoT data in batches of ' . self::BATCH_SIZE);

        ini_set('memory_limit', (string)PurgeAllCommand::MEMORY_LIMIT);

        $dateToArchive = new DateTime('-' . PurgeAllCommand::PURGE_IOT_DATA_OLDER_THAN . PurgeAllCommand::DATA_PURGE_THRESHOLD);

        $this->processIoTMessages($dateToArchive);


        $this->io->success('IoT data archiving completed.');

        return Command::SUCCESS;
    }

    private function processIoTMessages(DateTime $dateToArchive): void
    {
        $iotMessageRepository = $this->entityManager->getRepository(SensorMessage::class);
        $totalToArchive = $iotMessageRepository->countOlderThan($dateToArchive);
        $this->io->progressStart($totalToArchive);

        $batch = [];
        $batchCount = 0;

        // generate CSV file with IOT data older than the specified date
        exec('php bin/' . self::SCRIPT_NAME . " " . $dateToArchive->format('Y-m-d'));

        $messagesToArchive = $iotMessageRepository->iterateOlderThan($dateToArchive);

        foreach ($messagesToArchive as $message) {
            $batch[] = $message;
            $batchCount++;
            $this->io->progressAdvance();

            if ($batchCount === self::BATCH_SIZE) {
                $this->archiveBatch($batch, $iotMessageRepository);
                $batch = [];
                $batchCount = 0;

                if ($this->isMemoryLimitReached()) {
                    $this->io->warning('Memory limit reached. Stopping further processing.');
                    break;
                }
            }
        }

        if (!empty($batch)) {
            $this->archiveBatch($batch, $iotMessageRepository);
        }

        $this->io->progressFinish();
    }

    private function archiveBatch(array $messages, SensorMessageRepository $iotMessageRepository): void
    {
        /** @var SensorMessage $message */
        foreach ($messages as $message) {
            /** @var Sensor $sensor */
            $sensor = $message->getSensor();

            // Set last_message_id to null before deleting the message
            if ($sensor && $sensor->getLastMessage() === $message) {
                $sensor->setLastMessage(null);
            }

            $this->entityManager->remove($message);

            $countMessageForSensor = $iotMessageRepository->countBySensor($sensor);

            $this->io->title("Sensors messages count : " . $countMessageForSensor);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    private function isMemoryLimitReached(): bool
    {
        return memory_get_usage() > (PurgeAllCommand::MEMORY_LIMIT * PurgeAllCommand::MEMORY_USAGE_THRESHOLD);
    }
}
