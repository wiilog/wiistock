<?php

namespace App\Command;

use App\Entity\Pack;
use App\Entity\Tracking\TrackingMovement;
use App\Repository\PackRepository;
use App\Serializer\SerializerUsageEnum;
use App\Service\FormatService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\SerializerInterface;
use WiiCommon\Helper\Stream;


#[AsCommand(
    name: 'app:lu:remove-duplicate',
    description: 'Tool to remove duplicate LU',
)]
class RemoveDuplicateLUCommand extends Command {
    public function __construct(private EntityManagerInterface $entityManager,
                                private SerializerInterface    $serializer,
                                private FormatService          $formatService) {
        parent::__construct();
    }

    protected function configure(): void {}

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $luRepository = $this->entityManager->getRepository(Pack::class);
        $trackingMovementRepository = $this->entityManager->getRepository(TrackingMovement::class);
        $duplicateLUsData = $luRepository->findDuplicateCode();
        $duplicateGroupeData = [];
        $io = new SymfonyStyle($input, $output);
        $deletedEntities = [];

        // if there are no duplicate LUs, show a message in the console output
        if (empty($duplicateLUsData)) {
            $io->success('Pas d\'UL dupliquée !!');
            return Command::SUCCESS;
        } else {
            $io->warning('Il y a des ULs dupliquées !!');
        }

        foreach ($duplicateLUsData as $duplicateLUData) {
            $lus = $luRepository->findBy(['code' => $duplicateLUData['code']]);

            // if there are LUs with children, we keep them for processing later
            if (Stream::from($lus)->some(fn(Pack $lu) => count($luRepository->findBy(['parent' => $lu])))) {
                $duplicateGroupeData[] = $duplicateLUData;
                continue;
            }

            $this->treatDuplication($io, $lus, $duplicateLUData, $deletedEntities, $luRepository);
            //$this->entityManager->flush();

        }

        foreach ($duplicateGroupeData as $duplicateLUData) {
            $lus = $luRepository->findBy(['code' => $duplicateLUData['code']]);
            $this->treatDuplication($io, $lus, $duplicateLUData, $deletedEntities, $luRepository);
            //$this->entityManager->flush();
        }

        dump(json_encode($deletedEntities));
        // save the changes in the database after removing the duplicate LUs
        $this->entityManager->flush();

        return Command::SUCCESS;
    }

    private function treatDuplication($io, $lus, $duplicateLUData, &$deletedEntities, $luRepository): void
    {
        $io->section("ULs dupliquées : {$duplicateLUData['code']}");
        // show the duplicate LUs in the console output
        $io->table(...$this->createTableConfig($lus, $this->formatService, $luRepository));

        $firstMovements = Stream::from($lus)
            ->map(fn(Pack $lu) => $lu->getTrackingMovements()->first())
            ->filter() // remove null values
            ->toArray();

        // if anny of the duplicate LUs has a tracking movement
        if (empty($firstMovements)) {
            // we keep one lu
            // remove one element of $lus
            $luToKeep = array_shift($lus);
            $io->text("On garde l'UL avec l'id : {$luToKeep->getId()}");

            // remove the other LUs
            Stream::from($lus)
                ->each(function(Pack $lu) use (&$deletedEntities) {
                    $deletedEntities = $this->remove($lu, $deletedEntities);
                });
        }
        // if there is only one tracking movement, we can delete the other LUs
        elseif (count($firstMovements) === 1) {
            $firstMovement = $firstMovements[array_key_first($firstMovements)];
            $io->text("Le mouvement de traca le plus ancien est : {$firstMovement->getDatetime()->format('Y-m-d H:i:s')}");

            Stream::from($lus)
                ->filter(fn(Pack $lu) => $lu->getTrackingMovements()->first() !== $firstMovement)
                ->each(function (Pack $lu) use (&$deletedEntities) {
                    $deletedEntities = $this->remove($lu, $deletedEntities);
                });
        }
        // if there are multiple tracking movements
        elseif (count($firstMovements) > 1) {
            $io->text('Il y a plusieurs mouvements de traca');

            // remove the LUs that do not have a tracking movement
            Stream::from($lus)
                ->filter(fn(Pack $lu) => $lu->getTrackingMovements()->isEmpty())
                ->each(function (Pack $lu) use (&$deletedEntities) {
                    $deletedEntities = $this->remove($lu, $deletedEntities);
                });

            // serialize the first movements to compare them
            $firstMovements = Stream::from($firstMovements)
                ->map(fn($firstMovement) => json_encode($this->serializer->normalize($firstMovement, null, ["usage" => SerializerUsageEnum::MOBILE])))
                ->unique();

            //if every LU  have first movement
            if(count($firstMovements) === 1) {
                $io->text('Tous les ULs ont le même premier mouvement');

                $notGroupLu = Stream::from($lus)->filter(fn(Pack $lu) => !$luRepository->count(['parent' => $lu]))->toArray();
                // if there is only one LU with children
                if (count($lus) - count($notGroupLu) === 1) {
                    // we keep the LU with children
                    foreach ($notGroupLu as $lu) {
                        $deletedEntities = $this->remove($lu, $deletedEntities);
                    }
                }
                // there no LU with children
                elseif (count($lus) - count($notGroupLu)  === 0) {
                    // in this situation it should be one lu with many movements and the other with one movement
                    // we can remove the LUs with one movement
                    Stream::from($lus)
                        ->filter(fn(Pack $lu) => $lu->getTrackingMovements()->count() === 1)
                        ->each(function (Pack $lu) use (&$deletedEntities) {
                            $deletedEntities = $this->remove($lu, $deletedEntities);
                        });
                }
                // this not a normal situation
                else {
                    $io->error('Il y a un problème avec les ULs, Il y a deux groupes en doublons');
                }
            }
        }
    }

    private function remove(Pack $lu, $deletedEntities): array {
        $trackingMovementRepository = $this->entityManager->getRepository(TrackingMovement::class);

        Stream::from($lu->getTrackingMovements(), $trackingMovementRepository->findBy(["packParent" => $lu]))
            ->each( function (TrackingMovement $trackingMovement) use (&$deletedEntities) {
                $deletedEntities['trackingMovement'][] = $this->serializer->normalize($trackingMovement, null, ["usage" => SerializerUsageEnum::MOBILE]);
                $this->entityManager->remove($trackingMovement);
            });

        $deletedEntities['lu'][] = $this->serializer->normalize($lu, null, ["usage" => SerializerUsageEnum::MOBILE]);
        $this->entityManager->remove($lu);

        return $deletedEntities;
    }

    /**
     * @param Pack[] $lus
     */
    private function createTableConfig( array $lus, FormatService $formatService, PackRepository $luRepository): array {
        $header = ['Id', 'Dernier mouvement', 'nature', 'Emplacement', 'nb d\'ul enfants'];
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
            ];
        }
        return [$header, $table];
    }
}
