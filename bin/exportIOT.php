<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Command\Purge\PurgeAllCommand;
use App\Service\IOT\IOTService;

class SensorDataExporter
{
    private $connection;
    private int $batchSize = 10000;
    private int $offset = 0;
    public string $outputFile = 'export_iot_sensor_data.csv';

    // CSV columns definition
    private const COLUMNS = [
        'type_de_capteur',
        'profil_du_capteur',
        'nom_du_capteur',
        'code_du_capteur',
        'date_du_message',
        'donnee_du_message',
        'type_de_donnee_du_message',
        'type_de_message',
        'message_brut',
    ];

    public function __construct(string $host, string $dbname, string $username, string $password)
    {
        $this->connectToDatabase($host, $dbname, $username, $password);
    }

    /**
     * Establishes the database connection.
     */
    private function connectToDatabase(string $host, string $dbname, string $username, string $password): void
    {
        try {
            $this->connection = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8",
                $username,
                $password
            );
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            $this->handleError("Connexion échouée : " . $e->getMessage());
        }
    }

    /**
     * Handles errors.
     */
    private function handleError(string $message): void
    {
        echo $message;
        exit(1);
    }

    /**
     * Opens the CSV file for writing.
     * Returns a file resource.
     */
    private function openFile(): mixed
    {
        $file = fopen(__DIR__ . '/../' . $this->outputFile, 'w');
        if (!$file) {
            $this->handleError("Impossible d'ouvrir ou de créer le fichier '$this->outputFile'.");
        }
        return $file;
    }

    /**
     * Writes a row of data to the CSV file.
     */
    private function writeToCsv($file, array $rowData): void
    {
        fputcsv($file, $rowData);
    }

    /**
     * Exports data into a CSV file.
     */
    public function exportData(string $dateFilter = null): void
    {
        $file = $this->openFile();

        // Write header
        $this->writeToCsv($file, self::COLUMNS);

        do {
            $rows = $this->fetchData($dateFilter);

            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $row['type_de_donnee_du_message'] = IOTService::DATA_TYPE[$row['type_de_donnee_du_message_id']] ?? '';
                    unset($row['type_de_donnee_du_message_id']); // Remove ID after replacement
                    $rowData = $this->mapRowToCsv($row);
                    $this->writeToCsv($file, $rowData);
                }
                $this->offset += $this->batchSize;
            } else {
                break;
            }
        } while (true);

        fclose($file);
        echo "Les données ont été exportées dans '$this->outputFile'.\n";
    }

    /**
     * Fetches data from the database using batch size, offset, and optional date filter.
     */
    private function fetchData(?string $dateFilter): array
    {
        $sql = "
            SELECT
                type.label AS type_de_capteur,
                sensor_profile.name AS profil_du_capteur,
                sensor_wrapper.name AS nom_du_capteur,
                sensor.code AS code_du_capteur,
                sensor_message.date AS date_du_message,
                sensor_message.content AS donnee_du_message,
                sensor_message.content_type AS type_de_donnee_du_message_id,
                sensor_message.event AS type_de_message,
                sensor_message.payload AS message_brut
            FROM sensor
            LEFT JOIN type ON sensor.type_id = type.id
            LEFT JOIN sensor_profile ON sensor.profile_id = sensor_profile.id
            LEFT JOIN sensor_message ON sensor.last_message_id = sensor_message.id
            LEFT JOIN sensor_wrapper ON sensor.id = sensor_wrapper.sensor_id
            WHERE :dateFilter IS NULL OR sensor_message.date < :dateFilter
            LIMIT {$this->batchSize} OFFSET {$this->offset}
        ";

        $stmt = $this->connection->prepare($sql);
        $stmt->bindParam(':dateFilter', $dateFilter);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return [];
    }

    /**
     * Maps a row of data to the CSV format.
     */
    private function mapRowToCsv(array $row): array
    {
        return [
            $row['type_de_capteur'],
            $row['profil_du_capteur'],
            $row['nom_du_capteur'],
            $row['code_du_capteur'],
            $row['date_du_message'],
            $row['donnee_du_message'],
            $row['type_de_donnee_du_message'],
            $row['type_de_message'],
            $row['message_brut'],
        ];
    }

    public function getGeneratedOutputFile(string $dateFilter): string
    {
        $todayDate = new DateTime("now");
        // file name = ARC + entityToArchive + today's date + _ + $dateToArchive + .csv
        return PurgeAllCommand::ARCHIVE_DIR . "ARC_IOT_" . $todayDate->format("Y-m-d") . "_" . $dateFilter . ".csv";
    }
}

$exporter = new SensorDataExporter("mysql", "test", "root", "example");

// check if the dateFilter is set
if (isset($argv[1])) {
    $dateFilter = $argv[1];

    $exporter->outputFile = $exporter->getGeneratedOutputFile($dateFilter);

    $exporter->exportData($dateFilter);
} else {
    echo "Veuillez fournir une date au format YYYY-MM-DD.\n ";
    exit(1);
}

