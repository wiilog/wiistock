<?php

namespace App\Command;

use App\Entity\Article;
use App\Entity\Tracking\Pack;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// TODO WIIS-12167: remove
#[AsCommand(
    name: 'app:article:remove-duplicate',
    description: 'Tool to remove duplicate article',
)]
class RemoveDuplicateArticlesCommand extends Command {
    public function __construct(private  EntityManagerInterface $entityManager) {
        parent::__construct();
    }

    protected function configure(): void {}

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $luRepository = $this->entityManager->getRepository(Pack::class);
        $articleRepository = $this->entityManager->getRepository(Article::class);

        $duplicateArticlesData = $articleRepository->findDuplicateCode();
        $io = new SymfonyStyle($input, $output);

        if (empty($duplicateArticlesData)) {
            $io->success('No duplicate articles !!');
            return Command::SUCCESS;
        } else {
            $io->warning('There are duplicate articles !!');
        }

        foreach ($duplicateArticlesData as $duplicateArticleData) {
            $article = $articleRepository->findOneBy(['barCode' => $duplicateArticleData['barCode']]);
            $io->section("Duplicate articles : {$duplicateArticleData['barCode']}");

            $codeNumber = (int)substr($article->getbarCode(), strlen(Article::BARCODE_PREFIX));
            $newCode = Article::BARCODE_PREFIX . ($codeNumber + 10000000);
            $article->setbarCode($newCode);
            $packs = $luRepository->findBy(['article' => $article]);
            foreach ($packs as $pack) {
                $pack->setCode($newCode);
            }
            $this->entityManager->flush();
            $io->success("Article {$article->getBarCode()} has been renamed to {$newCode}");
        }
        return Command::SUCCESS;
    }
}
