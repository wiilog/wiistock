<?php

namespace App\Command;

use App\Entity\Pack;
use App\Entity\ProductionRequest;
use App\Repository\PackRepository;
use App\Service\FormatService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WiiCommon\Helper\Stream;


#[AsCommand(
    name: 'app:lu:remove-duplicate',
    description: 'Tool to remove duplicate LU',
)]
class RemoveDuplicateLUCommand extends Command {
    public function __construct(private readonly EntityManagerInterface $entityManager,
                                private readonly FormatService          $formatService) {
        parent::__construct();
    }

    protected function configure(): void {}

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $luRepository = $this->entityManager->getRepository(Pack::class);
        $duplicateLUsData = $luRepository->findDuplicateCode();
        $io = new SymfonyStyle($input, $output);

        // if there are no duplicate LUs, show a message in the console output
        if (empty($duplicateLUsData)) {
            $io->success('Pas d\'UL dupliquée !!');
            return Command::SUCCESS;
        } else {
            $io->warning('Il y a des ULs dupliquées !!');
        }

        foreach ($duplicateLUsData as $duplicateLUData) {
            do {
                $lus = $luRepository->findBy(['code' => $duplicateLUData['code']]);
                $io->section("ULs dupliquées : {$duplicateLUData['code']}");
                // show the duplicate LUs in the console output
                $io->table(...$this->createTableConfig($lus, $this->formatService, $luRepository));

                // ask the user to choose the LU to rename
                $chosenLU = $io->choice('Quel UL voulez-vous renommer ? (id)', Stream::from($lus)->map(fn(Pack $lu) => $lu->getId())->toArray());
                $newCodeIsValid = false;
                do {
                    // ask the user to enter the new code
                    $newCode = $io->ask('Entrez le nouveau code');

                    // check if the new code is already used
                    $newCodeIsValid = count($luRepository->findBy(['code' => $newCode])) === 0;
                    if ($newCodeIsValid === false) {
                        $io->error("Le code {$newCode} est déjà utilisé");
                        $newCode = null;
                    }

                } while ($newCodeIsValid === false);


                // update the chosen LU with the new code
                $lu = $luRepository->find($chosenLU);
                $lu->setCode($newCode);
                $this->entityManager->flush();

                $io->success("UL {$chosenLU} renommée en {$newCode}");
                $io->newLine();

            } while (count($luRepository->findBy(['code' => $duplicateLUData['code']])) > 1);
        }


        return Command::SUCCESS;
    }

    private function createTableConfig( array $lus, FormatService $formatService, PackRepository $luRepository): array {

        $header = ['Id', 'Dernier mouvement', 'nature', 'Enplacement', 'nb d\'ul enfants'];
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
