<?php

namespace App\Command;

use App\Entity\LocationClusterRecord;
use App\Entity\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Repository\PackRepository;
use App\Serializer\SerializerUsageEnum;
use App\Service\FormatService;
use App\Service\SpecificService;
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
    name: 'app:lu:remove-duplicate',
    description: 'Tool to remove duplicate LU',
)]
class RemoveDuplicateLUCommand extends Command {
    public function __construct(private EntityManagerInterface  $entityManager,
                                private SerializerInterface     $serializer,
                                private SpecificService         $specificService,
                                private TrackingMovementService $trackingMovementService,
                                private FormatService           $formatService) {
        parent::__construct();
    }

    const LINE_TO_ClEAR = 25;

    protected function configure(): void {
        $this->addOption('details', 'x', null, 'Show actions');
        $this->addOption('interact', 'i', null, 'Interact');
        $this->addOption('dry-run', 'd', null, 'Simulate');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        // allow more memory to be used
        //Allowed memory size of 268435456 bytes
        ini_set('memory_limit', '1024M');


        $luRepository = $this->entityManager->getRepository(Pack::class);
        $duplicateLUsData = $luRepository->findDuplicateCode();
        $duplicateGroupeData = [];
        $io = new SymfonyStyle($input, $output);
        $editedEntities = [];

        // if there are no duplicate LUs, show a message in the console output
        if (empty($duplicateLUsData)) {
            $io->success('Pas d\'UL dupliquée !!');
            return Command::SUCCESS;
        } else {
            $io->warning('Il y a des ULs dupliquées !!');
        }
        $io->progressStart(count($duplicateLUsData));

        foreach ($duplicateLUsData as $duplicateLUData) {
            $lus = $luRepository->findBy(['code' => $duplicateLUData['code']]);

            // if there are LUs with children, we keep them for processing later
            if (Stream::from($lus)->some(fn(Pack $lu) => count($luRepository->findBy(['parent' => $lu])))) {
                $duplicateGroupeData[] = $duplicateLUData;
                continue;
            }

            $io->progressAdvance();

            if($input->getOption('details')) {
                $io->newLine(self::LINE_TO_ClEAR);
            }

            $this->treatDuplication($io, $lus, $duplicateLUData, $editedEntities, $luRepository);

            if (!$input->getOption('dry-run')) {
                $this->entityManager->flush();
            }
        }

        foreach ($duplicateGroupeData as $duplicateLUData) {
            //clear the console output
            if ($input->getOption('details')) {
                $io->newLine(self::LINE_TO_ClEAR);
            }

            $io->progressAdvance();

            $lus = $luRepository->findBy(['code' => $duplicateLUData['code']]);
            $this->treatDuplication($io, $lus, $duplicateLUData, $editedEntities, $luRepository);

            if (!$input->getOption('dry-run')) {
                $this->entityManager->flush();
            }
        }

        $io->text(json_encode($editedEntities));
        // save the changes in the database after removing the duplicate LUs
        if (!$input->getOption('dry-run')) {
            $this->entityManager->flush();
        }

        return Command::SUCCESS;
    }

    private function treatDuplication($io, $lus, $duplicateLUData, &$editedEntities, $luRepository): void {
        global $input;
        if ($input->getOption('details')) {
            $io->section("ULs dupliquées : {$duplicateLUData['code']}");
            // show the duplicate LUs in the console output
            $io->table(...$this->createTableLuConfig($lus, $this->formatService, $luRepository));
        }
        $firstMovements = Stream::from($lus)
            ->map(fn(Pack $lu) => (
                Stream::from($lu->getTrackingMovements()) // get the first tracking movement of each LU
                    ->sort(fn(TrackingMovement $a, TrackingMovement $b) => $this->trackingMovementService->compareMovements($a, $b))
                    ->first()
            ))
            ->filter() // remove null values
            ->toArray();

        $notGroupLu = Stream::from($lus)
            ->filter(static fn(Pack $lu) => !$luRepository->count(['parent' => $lu]))
            ->toArray();

        if ($this->specificService->isCurrentClientNameFunction([SpecificService::CLIENT_QUENELLE, SpecificService::CLIENT_SAUCISSON_BRIOCHE])) {

            $counter = 0;
            /** @var Pack $pack */
            foreach ($lus as $pack) {
                $oldCode = $pack->getCode();
                $pack->setCode($oldCode . "-" . ($counter + 1));
                $io->text("UL $oldCode renommée {$pack->getCode()}");
                $counter++;
            }

            return;
        }


        // if there is only one LU with children
        if (count($lus) - count($notGroupLu) === 1) {
            if ($input->getOption('details')) {
                $io->warning('Il y a un seul UL avec des enfants');
                $io->text('On garde l\'UL avec des enfants');
            }
            // we keep the LU with children
            foreach ($notGroupLu as $lu) {
                $editedEntities = $this->remove($lu, $editedEntities, $io);
            }
        } // if there are two groups in the duplicate LUs
        elseif (count($lus) - count($notGroupLu) > 1) {
            if ($input->getOption('details')) {
                $io->warning('Il y des groupes en double');
                $io->text('On renomme le groupe dupliqué');
            }

            // we will rename de duplicate group
            // wee keep the main group
            // and rename the other group
            // like Groupe, Groupe_1, Groupe_2
            Stream::from($lus)
                ->sort(fn(Pack $a, Pack $b) => $this->trackingMovementService->compareMovements($a->getLastAction(), $b->getLastAction()))
                ->reverse()
                ->each(function (Pack $lu, $index) use ($io, $luRepository, &$editedEntities) {
                    if ($index !== 0) {
                        $lu->setCode($lu->getCode() . "_$index");
                    }
                    $editedEntities['renamedGroup'][] = $this->serializer->normalize($lu, null, ["usage" => SerializerUsageEnum::MOBILE]);
                });

            if ($input->getOption('interact')) {
                $io->confirm('continuer ?');
            }


        } else {
            // if anny of the duplicate LUs has a tracking movement
            if (empty($firstMovements)) {
                // we keep one lu
                // remove one element of $lus
                $luToKeep = array_shift($lus);
                if ($input->getOption('details')) {
                    $io->warning("Aucun mouvement de traca pour les ULs dupliquées");
                    $io->text("On garde l'UL avec l'id : {$luToKeep->getId()}");
                }

                // remove the other LUs
                Stream::from($lus)
                    ->each(function (Pack $lu) use ($io, &$editedEntities) {
                        $editedEntities = $this->remove($lu, $editedEntities, $io);
                    });
            } // if there is only one tracking movement, we can delete the other LUs
            elseif (Stream::from($lus)->every(fn(Pack $lu) => $lu->getTrackingMovements()->count() === 1)) {
                $firstMovement = $firstMovements[array_key_first($firstMovements)];
                if ($input->getOption('details')) {
                    $io->warning('Il y a un seul mouvement de traca pour les ULs dupliquées');
                    $io->text("on garde l'UL avec le mouvement de traca : ");
                    $io->table(...$this->createTableMovementConfig([$firstMovement], $this->formatService));
                }

                Stream::from($lus)
                    ->filter(fn(Pack $lu) => $lu->getTrackingMovements()->first() !== $firstMovement)
                    ->each(function (Pack $lu) use ($io, &$editedEntities) {
                        $editedEntities = $this->remove($lu, $editedEntities, $io);
                    });
            } // if there are multiple tracking movements
            elseif (count($firstMovements) > 1) {
                if ($input->getOption('details')) {
                    $io->warning('Il y a plusieurs mouvements de traca');
                }

                // remove the LUs that do not have a tracking movement
                Stream::from($lus)
                    ->filter(fn(Pack $lu) => $lu->getTrackingMovements()->isEmpty())
                    ->each(function (Pack $lu) use ($io, &$editedEntities) {
                        $editedEntities = $this->remove($lu, $editedEntities, $io);
                    });

                // serialize the first movements to compare them
                $firstMovementsSerialized = Stream::from($firstMovements)
                    ->map(fn($firstMovement) => json_encode(
                        $this
                            ->serializer
                            ->normalize($firstMovement, null, ["usage" => SerializerUsageEnum::MOBILE_DROP_MENU])
                    ))
                    ->unique();
                // if all the LUs have the same first movement
                if (count($firstMovementsSerialized) === 1 ) {
                    // there no LU with children
                    // in this situation it should be one lu with many movements and the other with one movement
                    // we can remove the LUs with one movement
                    if ($input->getOption('details')) {
                        $io->warning('Tous les ULs ont le même premier mouvement');
                        $io->text('Il n\'y a pas d\'UL avec des enfants');
                        $io->text('On garde l\'UL avec le plus de mouvements');
                    }


                    $luSorted = Stream::from($lus)
                        ->sort(fn(Pack $a, Pack $b) => $a->getTrackingMovements()->count() <=> $b->getTrackingMovements()->count())
                        ->reverse()
                        ->toArray();
                    array_shift($luSorted);
                    foreach ($luSorted as $lu) {
                        $editedEntities = $this->remove($lu, $editedEntities, $io);
                    };
                } // if the LUs do not have the same first movement
                else {
                    if ($input->getOption('details')) {
                        $io->warning('le uls n\'ont pas le même premier mouvement');
                        $io->table(...$this->createTableMovementConfig($firstMovements, $this->formatService));
                        $io->text('On garde l\'UL avec le plus récent mouvement');
                    }

                    // we keep the LU with the most recent movement
                    $lastMovements = Stream::from($lus)
                        ->map(fn(Pack $lu) => (
                            Stream::from($lu->getTrackingMovements()) // get the first tracking movement of each LU
                                ->sort(fn(TrackingMovement $a, TrackingMovement $b) => $this->trackingMovementService->compareMovements($a, $b))
                                ->last()
                        ))
                        ->sort(fn(TrackingMovement $a, TrackingMovement $b) => $this->trackingMovementService->compareMovements($a, $b))
                        ->last();

                    $luToDel = Stream::from($lus)
                        ->filter(static fn(Pack $lu) => $lu !== $lastMovements->getPack())
                        ->toArray();

                    foreach ($luToDel as $lu) {
                        $editedEntities = $this->remove($lu, $editedEntities, $io);
                    }
                }
            } else {
                if ($input->getOption('details')) {
                    $io->warning('Il y a un seul mouvement de traca pour les ULs dupliquées');
                    $io->text('On garde l\'UL avec le mouvement de traca');
                }

                // if there is only one tracking movement, we can delete the other LUs
                $lusToDelete = Stream::from($lus)
                    ->sort(static fn(Pack $a, Pack $b) => $a->getTrackingMovements()->count() <=> $b->getTrackingMovements()->count())
                    ->reverse()
                    ->toArray();

                array_shift($lusToDelete);

                Stream::from($lusToDelete)
                    ->each(function (Pack $lu) use ($io, &$editedEntities) {
                        $editedEntities = $this->remove($lu, $editedEntities, $io);
                    });
            }
        }
    }

    private function remove(Pack $lu, $editedEntities, $io): array {
        global $input;
        $trackingMovementRepository = $this->entityManager->getRepository(TrackingMovement::class);
        $locationClusterRecordRepository = $this->entityManager->getRepository(LocationClusterRecord::class);

        $mvtToDelete = Stream::from($lu->getTrackingMovements(), $trackingMovementRepository->findBy(["packParent" => $lu]));
        if ($input->getOption('details')) {
            $io->success("On supprime l'UL avec l'id : {$lu->getId()}");
            $io->table(...$this->createTableMovementConfig((clone $mvtToDelete)->toArray(), $this->formatService));
        }

        $mvtToDelete
            ->each(function (TrackingMovement $trackingMovement) use ($locationClusterRecordRepository, &$editedEntities, $io) {
                Stream::from($locationClusterRecordRepository->findBy(["lastTracking" => $trackingMovement]))
                    ->each(function (LocationClusterRecord $locationClusterRecord) use ($io, &$editedEntities) {
                        $editedEntities['deletedLocationClusterRecord'][] = [
                            "id" => $locationClusterRecord->getId(),
                        ];
                        $this->entityManager->remove($locationClusterRecord);
                    });
                $editedEntities['deletedTrackingMovement'][] = $this->serializer->normalize($trackingMovement, null, ["usage" => SerializerUsageEnum::MOBILE_DROP_MENU]);
                $this->entityManager->remove($trackingMovement);
            });

        if ($input->getOption('details')) {
            $io->success("Supprimed");
        }
        $editedEntities['deletedLu'][] = $this->serializer->normalize($lu, null, ["usage" => SerializerUsageEnum::MOBILE]);
        $this->entityManager->remove($lu);

        if ($input->getOption('interact')) {
            $io->confirm('continuer ?');
        }

        return $editedEntities;
    }

    /**
     * @param Pack[] $lus
     */
    private function createTableLuConfig(array $lus, FormatService $formatService, PackRepository $luRepository): array {
        $header = ['Id', 'Dernier mouvement', 'nature', 'Emplacement', 'nb d\'ul enfants', 'nb de mouvements', 'groupe?'];
        $table = [];

        foreach ($lus as $lu) {
            $lastAction = $lu->getLastAction();
            $lastOngoingDrop = $lu->getLastOngoingDrop();
            $nbChildren = $luRepository->count(["parent" => $lu]);
            $table[] = [
                $lu->getId(),
                $lastAction?->getDatetime()?->format('Y-m-d H:i:s') ?: 'N/A',
                $formatService->nature($lu->getNature()),
                $formatService->location($lastOngoingDrop?->getEmplacement()),
                $nbChildren,
                $lu->getTrackingMovements()->count(),
                $lu->isGroup() ? 'Oui' : 'Non',
            ];
        }
        return [$header, $table];
    }

    private function createTableMovementConfig(array $movements, FormatService $formatService): array {
        $header = ['id', 'ul', 'Type', 'Date', 'Location', 'Nature', 'Operator', 'Comment', 'Quantity'];
        $table = [];

        foreach ($movements as $movement) {
            $table[] = [
                $movement->getId(),
                $movement->getPack()?->getId(),
                ucfirst($formatService->status($movement->getType())),
                $formatService->datetime($movement->getDatetime()),
                $formatService->location($movement->getEmplacement()),
                $formatService->nature($movement->getOldNature()),
                $formatService->user($movement->getOperateur()),
                $formatService->html($movement->getCommentaire()),
                $movement->getQuantity(),
            ];
        }

        return [$header, $table];
    }

}
