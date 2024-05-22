<?php

namespace App\Command;

use App\Entity\Attachment;
use App\Entity\Dispute;
use App\Entity\Reception;
use App\Entity\ScheduledTask\Import;
use App\Entity\TrackingMovement;
use App\Entity\Traits\AttachmentTrait;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;
use WiiCommon\Helper\Stream;

#[AsCommand(
    name: AttachmentCleanCommand::COMMAND_NAME,
    description: 'Add a short description for your command',
)]
class AttachmentCleanCommand extends Command
{
    public const COMMAND_NAME = "app:attachment:clean";

    #[Required]
    public EntityManagerInterface $entityManager;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $attachmentRepository = $this->entityManager->getRepository(Attachment::class);

//        foreach ($metas as $meta) {
//            if($meta->getName() === Dispute::class){
//            $reflexionClass = new ReflectionClass($meta->getName());
//            dump($meta);
//            break;
//            }
//        }
        $attachmentsToRemove = $attachmentRepository->getUnusedAttachments();
        dump($attachmentsToRemove);
        //$this->delete($attachmentsToRemove);

        return Command::SUCCESS;
    }

    protected function delete(array $attachmentsToRemove): void
    {
        foreach ($attachmentsToRemove as $attachment) {
            try {
                dump($attachment->getId());
                $this->entityManager->remove($attachment);
                $this->entityManager->flush();

                dump("deleted");
            }catch (Throwable){
                if(!$this->entityManager->isOpen()){
                    $this->entityManager = new EntityManager($this->entityManager->getConnection(), $this->entityManager->getConfiguration());
                }
                dump('not deleted : ',$attachment->getId());
                continue;
            }
        }

    }
}
