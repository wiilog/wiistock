<?php

namespace App\Command;

use App\Service\Tracking\PackService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


#[AsCommand(
    name: CalculateTrackingDelayCommand::COMMAND_NAME,
    description: 'Calculate the tracking delay of the given logistic unit and save it in database.'
)]
class CalculateTrackingDelayCommand extends Command {
    public const COMMAND_NAME = 'app:tracking:calculate-delay';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private PackService $packService,
    ) {
        parent::__construct(self::COMMAND_NAME);
    }

    protected function configure(): void {
        $this
            ->addArgument('code', InputArgument::REQUIRED, 'The logistic unit code to recalculate delay.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $success = $this->packService->updateTrackingDelayWithPackCode($this->entityManager, $input->getArgument("code"));
        if ($success) {
            $this->entityManager->flush();
        }

        return Command::SUCCESS;
    }

}
