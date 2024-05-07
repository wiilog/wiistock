<?php

namespace App\Command;

use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorProfile;
use App\Entity\Type;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(
    name: 'app:iot:add',
    description: 'Add Iot Object(s).',
)]
class AddIotObjectCommand extends Command {
    #[Required]
    public EntityManagerInterface $entityManager;

    protected function configure(): void {
        $this
            ->addArgument('type', InputArgument::REQUIRED, 'Type of the object(s)')
            ->addArgument('profile', InputArgument::REQUIRED, 'Profile of the object(s)')
            ->addArgument('frequency', InputArgument::REQUIRED, 'Frequency of the object(s)')
            ->addArgument('code', InputArgument::IS_ARRAY, 'Code of the object(s)');
    }

    public function execute(InputInterface $input, OutputInterface $output): int {
        $type = $this->entityManager->getRepository(Type::class)->findOneBy(['label' => $input->getArgument('type')]);
        $profile = $this->entityManager->getRepository(SensorProfile::class)->findOneBy(['name' => $input->getArgument('profile')]);
        $frequency = $input->getArgument('frequency');
        $codes = $input->getArgument('code');

        if (!$type || !$profile) {
            $output->writeln('Type or Profile not found');
            return Command::FAILURE;
        }

        foreach ($codes as $code) {
            $sensor = (new Sensor())
                ->setCode($code)
                ->setFrequency($frequency)
                ->setProfile($profile)
                ->setType($type);

            $this->entityManager->persist($sensor);
        }

        $this->entityManager->flush();

        return Command::SUCCESS;
    }
}
