<?php

namespace App\Command;

use App\Entity\IOT\SensorMessage;
use App\Service\TranslationService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use InfluxDB2;
use InfluxDB2\Model\Dialect;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(
    name: 'app:influxdb',
    description: 'This commands generate the yaml translations.'
)]
class InsertInfluxDBCommand extends Command {

    #[Required]
    public EntityManagerInterface $entityManager;

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $client = new InfluxDB2\Client([
            "url" => "http://influxdb:8086",
            "token" => "8z964dq6s84dq6s5d4s6q5f4d65f4fdsq7f98dqsf78",
            "bucket" => "wiistock_local",
            "org" => "wiilog",
            "precision" => InfluxDB2\Model\WritePrecision::NS
        ]);

        for($index = 0; $index < 1003; $index++) {
dump($index);

        $stmt = $this->entityManager->getConnection()->prepare("
            SELECT sensor_message.date AS date_message,
                   sensor_message.sensor_id AS sensor_id,
                   sensor_message.event AS event,
                   sensor_message.content AS content,
                   sensor_message.content_type AS content_type
            FROM sensor_message
            WHERE sensor_message.id >= :min AND sensor_message.id < :max
        ");
        $iterator = $stmt
            ->executeQuery([
                'min' => $index * 8500,
                'max' => ($index + 1) * 8500,
            ])
            ->iterateAssociative();
//            $sensorMessages = $this->entityManager->createQueryBuilder()
//                ->from(SensorMessage::class, 'sensor_message')
//                ->select('sensor_message.date')
//                ->select('sensor_message.sensor')
//                ->where('s')
//                ->join()
//                ->setParameter('min', $index * 8500)
//                ->setParameter('max', ($index + 1) * 8500)
//
//                ->setMaxResults(15)
//                ->getQuery()
//                ->toIterable();
//        $stmt->ge
//            dump($index);
            $writeApi = $client->createWriteApi();


            /** @var SensorMessage $message */
            foreach ($iterator as $message) {
//                dump($message);
                $timestamp = \DateTime::createFromFormat('Y-m-d H:i:s', $message["date_message"])
                    ->getTimestamp();
                $point = InfluxDB2\Point::measurement("sensor_message")
                    ->time($timestamp)
                    ->addTag("date", $message["date_message"])
                    ->addTag("sensor_id", $message['sensor_id'])
                    ->addTag("event", $message['event'])
                    ->addTag("content_type", $message['content_type'])
//                ->addField("payload", json_encode($message->getPayload()))
                    ->addField("content", $message['content']);

                $writeApi->write($point);
            }
        }

/*
        $queryApi = $client->createQueryApi();
        $result = $queryApi->queryStream('from(bucket: "wiistock_local") |> range(start: -1d)');

        foreach ($result->tables as $item) {

            dump($item);
        }

*/
        $client->close();

        return 0;
    }

}
