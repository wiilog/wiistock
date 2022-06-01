<?php

namespace App\Command;

use App\Entity\Setting;
use App\Entity\Transport\TransportRound;
use App\Service\CSVExportService;
use App\Service\Transport\TransportRoundService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use phpseclib3\Net\SFTP;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;

class TransportRoundUploadFTPCommand extends Command {

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public CSVExportService $csvExportService;

    #[Required]
    public TransportRoundService $transportRoundService;

    protected function configure() {
		$this->setName('app:transports:export-rounds');
		$this->setDescription('This commands upload export rounds to a SFTP Server.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {

        $settingRepository = $this->entityManager->getRepository(Setting::class);
        $transportRoundRepository = $this->entityManager->getRepository(TransportRound::class);

        $strServer = $settingRepository->getOneParamByLabel(Setting::FTP_ROUND_SERVER_NAME);
        $strServerPort = $settingRepository->getOneParamByLabel(Setting::FTP_ROUND_SERVER_PORT);
        $strServerUsername = $settingRepository->getOneParamByLabel(Setting::FTP_ROUND_SERVER_USER);
        $strServerPassword = $settingRepository->getOneParamByLabel(Setting::FTP_ROUND_SERVER_PASSWORD);
        $strServerPath = $settingRepository->getOneParamByLabel(Setting::FTP_ROUND_SERVER_PATH);

        if (!$strServer || !$strServerPort || !$strServerUsername || !$strServerPassword || !$strServerPath) {
            throw new \RuntimeException('Invalid settings');
        }

        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");
        $nameFile = "export-tournees-$today.csv";

        $csvHeader = $this->transportRoundService->getHeaderRoundAndRequestExport();

        $transportRoundsIterator = $transportRoundRepository->iterateFinishedTransportRounds();

        $output = tmpfile();

        $this->csvExportService->putLine($output, $csvHeader);

        /** @var TransportRound $round */
        foreach ($transportRoundsIterator as $round) {

            $this->transportRoundService->putLineTodayRoundAndRequest($output, $this->csvExportService, $round);
        }

        // we go back to the file begin to send all the file
        fseek($output, 0);

        try {
            $sftp = new SFTP($strServer, intval($strServerPort));
            $sftp_login = $sftp->login($strServerUsername, $strServerPassword);
            if ($sftp_login) {
                $trailingChar = $strServerPath[strlen($strServerPath) - 1];
                $sftp->put($strServerPath . ($trailingChar !== '/' ? '/' : '') . $nameFile, $output, SFTP::SOURCE_LOCAL_FILE);
            }
        }
        catch(Throwable $throwable) {
            fclose($output);
            throw $throwable;
        }

        fclose($output);

        return 0;
    }
}
