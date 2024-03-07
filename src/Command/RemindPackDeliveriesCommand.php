<?php
// Each day at 08:00
// 0 8 * * *

namespace App\Command;

use App\Entity\Setting;
use App\Service\PackService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(
    name: RemindPackDeliveriesCommand::COMMAND_NAME,
    description: 'This command sends emails when a pack is on a location group (or location if no group) more than 15 days.'
)]
class RemindPackDeliveriesCommand extends Command
{
    public const COMMAND_NAME= "app:emails:remind-pack-deliveries";

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public PackService $packService;

    protected function configure(): void {
        $this->setHelp('This command is supposed to be executed each day at 8 AM');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $settingRepository = $this->entityManager->getRepository(Setting::class);
        $sendEmail = $settingRepository->getOneParamByLabel(Setting::SEND_PACK_DELIVERY_REMIND);

        if ($sendEmail) {
            $this->packService->launchPackDeliveryReminder($this->entityManager);
        }

        return 0;
    }
}
