<?php

namespace App\Command;

use App\Entity\LocationClusterRecord;
use App\Entity\Pack;
use App\Entity\ReferenceArticle;
use App\Entity\Tracking\TrackingMovement;
use App\Repository\PackRepository;
use App\Serializer\SerializerUsageEnum;
use App\Service\FormatService;
use App\Service\TrackingMovementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\SerializerInterface;
use WiiCommon\Helper\Stream;

// TODO WIIS-12167: remove
#[AsCommand(
    name: 'app:ref-art:remove-duplicate',
    description: 'Tool to remove duplicate Reference Articles',
)]
class RemoveDuplicateRefArtCommand extends Command {
    public function __construct(private EntityManagerInterface  $entityManager,
                                private SerializerInterface     $serializer,
                                private FormatService           $formatService) {
        parent::__construct();
    }

    const LINE_TO_ClEAR = 25;

    protected function configure(): void {
        $this->addOption('interact', 'i', null, 'Interact');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        // allow more memory to be used
        //Allowed memory size of 268435456 bytes
        ini_set('memory_limit', '1024M');

        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);
        $duplicateRAsData = $referenceArticleRepository->findDuplicateCode();

        $io = new SymfonyStyle($input, $output);
        $editedEntities = [];

        // if there are no duplicate reference articles
        if (empty($duplicateRAsData)) {
            $io->success('Pas de Reference Articles dupliquées');
            return Command::SUCCESS;
        } else {
            $io->warning('Il y a des Reference Articles dupliquées');
        }


        $duplicateRACount = $referenceArticleRepository->count(['barCode' => Stream::from($duplicateRAsData)->map(fn($data) => $data['barCode'])->toArray()]);

        $io->progressStart($duplicateRACount);

        foreach ($duplicateRAsData as $duplicateRAData) {
            $referencesArticles = $referenceArticleRepository->findBy(['barCode' => $duplicateRAData['barCode']]);

            $this->treatDuplication($io, $referencesArticles, $duplicateRAData, $editedEntities, $referenceArticleRepository);


            $this->entityManager->flush();
        }

        $io->text(json_encode($editedEntities));
        $this->entityManager->flush();


        return Command::SUCCESS;
    }

    private function treatDuplication($io, $referencesArticles, $duplicateRAData, &$editedEntities, $referenceArticleRepository): void {
        global $input;

        // wee need to keep one reference article who keep the same code, so wee remove one element from the array
        $referencesArticles = array_slice($referencesArticles, 1);
        $io->progressAdvance();
        $newCodeTryCount = 0;
        foreach ($referencesArticles as $index => $referenceArticle) {
            $oldCode = $referenceArticle->getBarCode();
            // REF240900000001
            // dateCode = 2409
            $dateCode = substr($oldCode, 3, 4);

            //$io->warning("dateCode:$dateCode");

            // getHighestBarCodeByDateCode
            $highestBarCodeForDateCode = $referenceArticleRepository->getHighestBarCodeByDateCode($dateCode) ?? $oldCode;

            // code = REF240900000001 => 00000001
            $highestCodeNumber =  (int) substr($highestBarCodeForDateCode, -8);

            //$io->warning("highestCodeNumber:$highestBarCodeForDateCode, $highestCodeNumber");

            $newCodeTryCount = 0;
            do {
                // sprintf('%08u', $highestCounter + 1)
                $newCode = "REF" . $dateCode . sprintf('%08u', $newCodeTryCount + $highestCodeNumber + 1);
                $referenceArticleWithNewCode = $referenceArticleRepository->findOneBy(['barCode' => $newCode]);
                $newCodeTryCount++;

                //$io->text(" newCodeTryCount: $newCodeTryCount");

            } while ($referenceArticleWithNewCode);

            // $io->warning("ca y est on a un nouveau code : $newCode");

            $pack = $referenceArticle->getTrackingPack();

            if ($pack?->getBarCode() === $oldCode) {
                $pack->setBarCode($newCode);
            }

            $referenceArticle->setBarCode($newCode);


            $this->entityManager->flush();
            $io->progressAdvance();
        }

        if ($input->getOption('interact')) {
            $io->confirm('continuer ?');
        }
    }
}
