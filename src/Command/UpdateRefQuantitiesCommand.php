<?php

namespace App\Command;

use App\Entity\PreparationOrder\PreparationOrderReferenceLine;
use App\Entity\Livraison;
use App\Entity\PreparationOrder\Preparation;
use App\Entity\ReferenceArticle;
use App\Service\FormatService;
use App\Service\RefArticleDataService;
use App\Service\TranslationService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;

#[AsCommand(
    name: 'app:recalc:quantity',
    description: 'This command recalc refs quantities.'
)]
class UpdateRefQuantitiesCommand extends Command
{
    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public RefArticleDataService $refArticleService;

    #[Required]
    public FormatService $formatService;

    #[Required]
    public TranslationService $translation;

    protected function configure(): void
    {
        $this
            ->addArgument('ref', InputArgument::REQUIRED, 'La référence à mettre à jour.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): null|int|string|bool {
        $refToUpdate = $input->getArgument('ref');
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);
        $referenceArticleToUpdate = $referenceArticleRepository->findOneBy(['reference' => $refToUpdate]);
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
        $this->refArticleService->updateRefArticleQuantities($this->entityManager, $referenceArticleToUpdate, true);
        $this->entityManager->flush();
        $this->refArticleService->treatAlert($this->entityManager, $referenceArticleToUpdate);
        $this->entityManager->flush();
        $output
            ->writeln('Quantité disponible après mise à jour : ' . $referenceArticleToUpdate->getQuantiteDisponible() ?? 0);
        $output
            ->writeln('Quantité réservée après mise à jour : ' . $referenceArticleToUpdate->getQuantiteReservee() ?? 0);
        $output
            ->writeln('Quantité en stock après mise à jour : ' . $referenceArticleToUpdate->getQuantiteStock() ?? 0);
        $output
            ->writeln('');
        $refPrepasEnCours = $referenceArticleToUpdate
            ->getPreparationOrderReferenceLines()
            ->filter(function (PreparationOrderReferenceLine $ligneArticlePreparation) {
                $preparation = $ligneArticlePreparation->getPreparation();
                $statusCode = $preparation->getStatut()?->getCode();
                return in_array($statusCode, [
                    Preparation::STATUT_EN_COURS_DE_PREPARATION,
                    Preparation::STATUT_A_TRAITER
                ]);
            })
            ->map(function (PreparationOrderReferenceLine $ligneArticlePreparation) {
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
            ->getPreparationOrderReferenceLines()
            ->filter(function (PreparationOrderReferenceLine $ligneArticlePreparation) {
                $preparation = $ligneArticlePreparation->getPreparation();
                $livraison = $preparation->getLivraison();
                return isset($livraison) && $preparation->getStatut()?->getCode() === Livraison::STATUT_A_TRAITER;
            })
            ->map(function (PreparationOrderReferenceLine $ligneArticlePreparation) {
                $preparation = $ligneArticlePreparation->getPreparation();
                $livraison = $preparation->getLivraison();
                return $livraison->getNumero();
            });
        if ($refLivraisonEnCours->count() > 0) {
            $output
                ->writeln($this->translation->translate("Ordre", "Livraison", "Livraison", false) . 's en cours pour la référence ' . $refToUpdate);
            $output
                ->writeln($refLivraisonEnCours);
        } else {
            $output
                ->writeln('Aucune ' . mb_strtolower($this->translation->translate("Ordre", "Livraison", "Livraison", false)) . ' en cours pour la référence ' . $refToUpdate);
        }
        $output
            ->writeln('');
        return 0;
    }
}
