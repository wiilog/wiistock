<?php

namespace App\Command;

use App\Entity\Setting;
use App\Entity\Transport\TransportRound;
use App\Service\CSVExportService;
use App\Service\TranslationService;
use App\Service\Transport\TransportRoundService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use phpseclib3\Net\SFTP;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TransportRoundUploadFTPCommand extends Command {

    private $entityManager;
    private $csvExportService;
    private $transportRoundService;

    public function __construct(EntityManagerInterface $entityManager, CSVExportService $csvExportService, TransportRoundService $transportRoundService) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->csvExportService = $csvExportService;
        $this->transportRoundService = $transportRoundService;
    }

    protected function configure() {
		$this->setName('app:upload:rounds');
		$this->setDescription('This commands upload the export round to a SFTP Server.');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {

        $transportRoundRepository = $this->entityManager->getRepository(TransportRound::class);
        $today = new DateTime();
        $today = $today->format("d-m-Y H:i:s");
        $nameFile = "export-tournées-$today.csv";
        $csvHeader = [
            'N°Tournée',
            'Date Tournée',
            'Transport',
            'Livreur',
            'Immatriculation',
            'Kilomètres',
            'N° dossier patient',
            'N°Demande',
            'Adresse transport',
            'Métropole',
            'Numéro dans la tournée',
            'Urgence',
            'Date de création',
            'Demandeur',
            'Date demandée',
            'Date demande terminée',
            'Objets',
            'Anomalie température',
        ];

        $transportRoundsIterator = $transportRoundRepository->iterateTransportRoundsFinished();

        $output = fopen('export.csv', 'w+');

        $this->csvExportService->putLine($output, $csvHeader);

        /** @var TransportRound $round */
        foreach ($transportRoundsIterator as $round) {
            $this->transportRoundService->putRoundsLineParameters($output, $this->csvExportService, $round);
        }

        $settingRepository = $this->entityManager->getRepository(Setting::class);
        $strServer = $settingRepository->getOneParamByLabel(Setting::FTP_ROUND_SERVER_NAME);
        $strServerPort = $settingRepository->getOneParamByLabel(Setting::FTP_ROUND_SERVER_PORT);
        $strServerUsername = $settingRepository->getOneParamByLabel(Setting::FTP_ROUND_SERVER_USER);
        $strServerPassword = $settingRepository->getOneParamByLabel(Setting::FTP_ROUND_SERVER_PASSWORD);
        $strServerPATH = $settingRepository->getOneParamByLabel(Setting::FTP_ROUND_SERVER_PATH);

        $sftp = new SFTP($strServer, intval($strServerPort));
        $sftp_login = $sftp->login($strServerUsername, $strServerPassword);
        fclose($output);
        if($sftp_login) {
            $sftp->put($strServerPATH.$nameFile, 'export.csv', SFTP::SOURCE_LOCAL_FILE);
        }
        unlink('export.csv');
        return 0;
    }
}
