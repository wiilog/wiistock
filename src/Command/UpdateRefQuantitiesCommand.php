<?php

namespace App\Command;

use App\Entity\LigneArticlePreparation;
use App\Entity\Livraison;
use App\Entity\Preparation;
use App\Entity\ReferenceArticle;
use App\Service\RefArticleDataService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateRefQuantitiesCommand extends Command
{
    protected static $defaultName = 'app:recalc:quantity';

    private $em;
    private $refArticleService;

    public function __construct(EntityManagerInterface $entityManager, RefArticleDataService $refArticleDataService)
    {
        parent::__construct(self::$defaultName);
        $this->em = $entityManager;
        $this->refArticleService = $refArticleDataService;
    }

    protected function configure()
    {
        $this->setDescription('This command recalc refs quantities.');
        $this
            ->addArgument('ref', InputArgument::REQUIRED, 'La référence à mettre à jour.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $refToUpdate = $input->getArgument('ref');
        $referenceArticleRepository = $this->em->getRepository(ReferenceArticle::class);
        $referenceArticleToUpdate = $referenceArticleRepository->findOneByReference($refToUpdate);
        $output
            ->writeln('Quantité disponible avant mise à jour : ' . $referenceArticleToUpdate->getQuantiteDisponible() ?? 0);
        $output
            ->writeln('Quantité réservée avant mise à jour : ' . $referenceArticleToUpdate->getQuantiteReservee() ?? 0);
        $output
            ->writeln('Quantité en stock avant mise à jour : ' . $referenceArticleToUpdate->getQuantiteStock() ?? 0);
        $output
            ->writeln('');
        $output
            ->writeln('..........Mise à jour..........');
        $output
            ->writeln('');
        $this->refArticleService->updateRefArticleQuantities($referenceArticleToUpdate, true);
        $this->em->flush();
        $this->refArticleService->treatAlert($referenceArticleToUpdate);
        $this->em->flush();
        $output
            ->writeln('Quantité disponible après mise à jour : ' . $referenceArticleToUpdate->getQuantiteDisponible() ?? 0);
        $output
            ->writeln('Quantité réservée après mise à jour : ' . $referenceArticleToUpdate->getQuantiteReservee() ?? 0);
        $output
            ->writeln('Quantité en stock après mise à jour : ' . $referenceArticleToUpdate->getQuantiteStock() ?? 0);
        $output
            ->writeln('');
        $refPrepasEnCours = $referenceArticleToUpdate
            ->getLigneArticlePreparations()
            ->filter(function (LigneArticlePreparation $ligneArticlePreparation) {
                $preparation = $ligneArticlePreparation->getPreparation();
                return $preparation->getStatut()->getNom() === Preparation::STATUT_EN_COURS_DE_PREPARATION
                    || $preparation->getStatut()->getNom() === Preparation::STATUT_A_TRAITER;
            })
            ->map(function (LigneArticlePreparation $ligneArticlePreparation) {
                $preparation = $ligneArticlePreparation->getPreparation();
                return $preparation->getNumero();
            });
        if ($refPrepasEnCours->count() > 0) {
            $output
                ->writeln('Préparations en cours pour la référence ' . $refToUpdate);
            $output
                ->writeln($refPrepasEnCours);
        } else {
            $output
                ->writeln('Aucune préparation en cours pour la référence ' . $refToUpdate);
        }
        $output
            ->writeln('');
        $refLivraisonEnCours = $referenceArticleToUpdate
            ->getLigneArticlePreparations()
            ->filter(function (LigneArticlePreparation $ligneArticlePreparation) {
                $preparation = $ligneArticlePreparation->getPreparation();
                $livraison = $preparation->getLivraison();
                return isset($livraison) && $livraison->getStatut()->getNom() === Livraison::STATUT_A_TRAITER;
            })->map(function (LigneArticlePreparation $ligneArticlePreparation) {
                $preparation = $ligneArticlePreparation->getPreparation();
                $livraison = $preparation->getLivraison();
                return $livraison->getNumero();
            });
        if ($refLivraisonEnCours->count() > 0) {
            $output
                ->writeln('Livraisons en cours pour la référence ' . $refToUpdate);
            $output
                ->writeln($refLivraisonEnCours);
        } else {
            $output
                ->writeln('Aucune livraison en cours pour la référence ' . $refToUpdate);
        }
        $output
            ->writeln('');
    }
}
