<?php
// Each day at 08:00
// 0 8 * * *

namespace App\Command\Emails;

use App\Entity\ParametrageGlobal;
use App\Service\PackService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RemindPackDeliveriesCommand extends Command
{
    protected static $defaultName = 'app:emails:remind-pack-deliveries';

    /** @Required */
    public EntityManagerInterface $entityManager;

    /** @Required */
    public PackService $packService;

    protected function configure(): void {
		$this->setDescription('This commands send emails when a pack is on a location group (or location if no group) more than 15 days.');
        $this->setHelp('This command is supposed to be executed each day at 8 AM');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $parametrageGlobalRepository = $this->entityManager->getRepository(ParametrageGlobal::class);
        $sendEmail = $parametrageGlobalRepository->getOneParamByLabel(ParametrageGlobal::SEND_PACK_DELIVERY_REMIND);

        if ($sendEmail) {
            $this->packService->launchPackDeliveryReminder($this->entityManager);
        }

        return 0;
    }
}
