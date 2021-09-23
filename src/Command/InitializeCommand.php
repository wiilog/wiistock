<?php

namespace App\Command;

use App\Entity\ParametrageGlobal;
use App\Service\GlobalParamService;
use App\Service\WiilockService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

class InitializeCommand extends Command {

    protected static $defaultName = "app:initialize";

    /** @Required */
    public GlobalParamService $globalParamService;

    /** @Required */
    public EntityManagerInterface $entityManager;

    /** @Required */
    public WiilockService $wiilockService;

    protected function configure(): void {
        $this->setDescription("Initializes the application");
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int {
        if ($this->wiilockService->dashboardIsBeingFed($this->entityManager)) {
            $output->writeln("Dashboards were locked, unlocking");
            $this->wiilockService->toggleFeedingDashboard($this->entityManager, false);
        } else {
            $output->writeln("Dashboards were not locked");
        }

        $this->globalParamService->generateScssFile();
        $this->globalParamService->generateSessionConfig();

        $this->entityManager->flush();

        return 0;
    }

}
