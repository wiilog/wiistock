<?php

namespace App\Command;

use App\Entity\Pack;
use App\Repository\PackRepository;
use App\Service\FormatService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:lu:remove-duplicate',
    description: 'Tool to remove duplicate LU',
)]
class RemoveDuplicateLUCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FormatService $formatService
    ) {
        parent::__construct();
    }

    protected function configure(): void {}

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $luRepository = $this->entityManager->getRepository(Pack::class);
        $duplicateLUsData = $luRepository->findDuplicateCode();
        $io = new SymfonyStyle($input, $output);

        if (empty($duplicateLUsData)) {
            $io->success('Aucun code UL en double trouvé.');
            return Command::SUCCESS;
        } else {
            $io->warning('Codes UL en double trouvés.');
        }

        foreach ($duplicateLUsData as $duplicateLUData) {
            $lus = $luRepository->findBy(['code' => $duplicateLUData['code']]);
            $io->section("Code UL dupliqué: {$duplicateLUData['code']} (" . count($lus) . " occurrences)");
            $io->table(...$this->createTableConfig($lus, $this->formatService, $luRepository));

            if (strlen($duplicateLUData['code']) < 250) {
                // Automatically apply the "Add counter" choice
                $this->applyCounter($lus, $duplicateLUData['code'], $luRepository, $io);
            } else {
                // Offer choices to the user
                $this->offerChoices($lus, $duplicateLUData['code'], $luRepository, $io);
            }
        }

        return Command::SUCCESS;
    }

    private function applyCounter(array $lus, string $baseCode, PackRepository $luRepository, SymfonyStyle $io): void
    {
        $counter = 1;
        foreach ($lus as $lu) {
            $newCodeIsValid = false;
            while (!$newCodeIsValid) {
                $newCode = $baseCode . '_' . sprintf("%02d", $counter);
                $newCodeIsValid = count($luRepository->findBy(['code' => $newCode])) === 0;
                if (!$newCodeIsValid) {
                    $counter++;
                }

                if (strlen($newCode) > 250) {
                    $io->error("Le code {$newCode} dépasse 250 caractéres");
                    $newCodeIsValid = false;
                    $counter++;
                    break;
                }
            }

            if ($newCodeIsValid) {
                $lu->setCode($newCode);
                $this->entityManager->flush();
                $io->success("UL {$lu->getId()} renommé =>  {$newCode}");
                $io->newLine();
            }
        }
    }

    private function offerChoices(array $lus, string $baseCode, PackRepository $luRepository, SymfonyStyle $io): void
    {
        $validChoice = false;
        while (!$validChoice) {
            $choice = $io->choice('Que voulez vous faire ?', ['Ajouter un compteur', 'Renommer completement', 'Passer à UL suivante']);

            if ($choice === 'Ajouter un compteur') {
                $this->applyCounter($lus, $baseCode, $luRepository, $io);
                $validChoice = true;
            } elseif ($choice === 'Renommer completement') {
                $this->renameCompletely($lus, $baseCode, $luRepository, $io);
                $validChoice = true;
            } elseif ($choice === 'Passer à UL suivante') {
                $io->note("Passer à UL suivante sans renommer UL actuelle.");
                $validChoice = true;
                break;
            }
        }
    }

    private function renameCompletely(array $lus, string $baseCode, PackRepository $luRepository, SymfonyStyle $io): void
    {
        $newCodeBase = $io->ask('Entrez le nouveau code UL', $baseCode . '_bis');
        $counter = 1;
        foreach ($lus as $lu) {
            $newCodeIsValid = false;
            while (!$newCodeIsValid) {
                $newCode = $newCodeBase . '_' . sprintf("%02d", $counter);
                $newCodeIsValid = count($luRepository->findBy(['code' => $newCode])) === 0;
                if (!$newCodeIsValid) {
                    $counter++;
                }

                if (strlen($newCode) > 255) {
                    $io->error("Le  code {$newCode} dépasse la limite de 250 caractéres.");
                    $newCodeIsValid = false;
                    $counter++;
                }
            }

            $lu->setCode($newCode);
            $this->entityManager->flush();
            $io->success("UL {$lu->getId()} renommée en {$newCode}");
            $io->newLine();
        }
    }

    private function createTableConfig(array $lus, FormatService $formatService, PackRepository $luRepository): array
    {
        $header = ['Id', 'Dernier mouvement', 'Nature', 'Emplacement actuel', 'n\'ombre d\'UL enfants'];
        $table = [];

        foreach ($lus as $lu) {
            $lastTracking = $lu->getLastTracking();
            $lastDrop = $lu->getLastDrop();
            $nbChildren = $luRepository->count(["parent" => $lu]);
            $table[] = [
                $lu->getId(),
                $lastTracking?->getDatetime()?->format('Y-m-d H:i:s') ?: 'N/A',
                $formatService->nature($lu->getNature()),
                $formatService->location($lastDrop?->getEmplacement()),
                $nbChildren,
            ];
        }

        return [$header, $table];
    }
}
