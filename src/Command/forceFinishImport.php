<?php

namespace App\Command;

use App\Entity\CategorieStatut;
use App\Entity\ScheduledTask\Import;
use App\Entity\Statut;
use App\Service\ImportService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:finish-import',
    description: 'This command is made to force finish a broken import',
)]
class forceFinishImport extends Command {
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this
            ->addArgument('id', InputArgument::REQUIRED, 'The id of the import to finish');
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int {
        $io = new SymfonyStyle($input, $output);

        $importId = $input->getArgument('id');
        $entityManager = $this->entityManager;

        $statusRepository = $entityManager->getRepository(Statut::class);
        $importRepository = $entityManager->getRepository(Import::class);

        $import = $importRepository->find($importId);

        if(!$import) {
            $io->error('Import not found');
            return 1;
        }

        if($import->getStatus()->getCode() !== Import::STATUS_IN_PROGRESS) {
            $io->error('Import is not in progress');
            return 1;
        }

        $io->success('Import found');

        if ($io->confirm("Are you sure you want to finish the import?", false)) {
            $statusFinished = $statusRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::IMPORT, Import::STATUS_FINISHED);
            $now = new DateTime();
            $import
                ->setStatus($statusFinished)
                ->setForced(false)
                ->setEndDate($now);
            $entityManager->flush();
        }

        return 0;
    }
}
