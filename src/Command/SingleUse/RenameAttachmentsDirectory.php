<?php

namespace App\Command\SingleUse;

use App\Service\AttachmentService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Permet de transformer un fichier excel de traductions en
 * variable PHP qui contient ces traductions.
 *
 * Ne pas supprimer, peut servir plus tard si de gros pans
 * de l'application sont de nouveau traduit d'un coup.
 */
class RenameAttachmentsDirectory extends Command {

    #[Required]
    public KernelInterface $kernel;

    #[Required]
    public AttachmentService $attachmentService;

    protected function configure(): void {
        $this->setName("app:rename-attachments-directory")
            ->setDescription("Rename attachements to attachments");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $currentAttachmentsDirectory = "{$this->kernel->getProjectDir()}/public/uploads/attachments";
        $newAttachmentsDirectory = $this->attachmentService->getAttachmentDirectory();

        if (file_exists($currentAttachmentsDirectory)) {
            rename($currentAttachmentsDirectory, $newAttachmentsDirectory);
        }

        return 0;
    }

}
