<?php


namespace App\Service;

use App\Entity\ArticleFournisseur;
use App\Entity\Fournisseur;
use App\Entity\ReferenceArticle;
use Doctrine\ORM\EntityManagerInterface;
use Exception;


class ArticleFournisseurService
{

    public const ERROR_REFERENCE_ALREADY_EXISTS = "reference-already-exists";
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this ->entityManager = $entityManager;
    }


    /**
     * @param array $data
     * @param bool $generateReference
     * @return ArticleFournisseur
     * @throws Exception
     */
    public function createArticleFournisseur(array $data, bool $generateReference = false, EntityManagerInterface $entityManager = null): ArticleFournisseur
    {
        if (!isset($entityManager)) {
            $entityManager = $this->entityManager;
        }

        $articleFournisseurRepository = $entityManager->getRepository(ArticleFournisseur::class);
        $fournisseurRepository = $entityManager->getRepository(Fournisseur::class);
        $referenceArticleRepository = $entityManager->getRepository(ReferenceArticle::class);

        $fournisseur = ($data['fournisseur'] instanceof Fournisseur)
            ? $data['fournisseur']
            : (intval($data['fournisseur'])
                ? $fournisseurRepository->find(intval($data['fournisseur']))
                : $fournisseurRepository->findOneBy(["codeReference" => $data['fournisseur']])
            );

        $referenceArticle = ($data['article-reference'] instanceof ReferenceArticle)
            ? $data['article-reference']
            : $referenceArticleRepository->find(intval($data['article-reference']));

        $label = $data['label'] ?: null;

        if ($generateReference) {
            $countReference = $articleFournisseurRepository->count([
                'referenceArticle' => $referenceArticle,
                'fournisseur' => $fournisseur
            ]);
        }
        else {
            $countReference = $articleFournisseurRepository->countByReference($data['reference']);
        }

        if ($generateReference || $countReference === 0) {
            $referencePrefix = $data['reference'];

            if ($generateReference) {
                $generatedCounter = $countReference;
                do {
                    $generatedCounter++;
                    $generatedReference = ($referencePrefix . '_' . $generatedCounter);
                    $countReference = $articleFournisseurRepository->countByReference($generatedReference);
                }
                while ($countReference > 0);
            }
            else {
                $generatedReference = $referencePrefix;
            }

            $isVisible = $referenceArticle->getStatut()->getCode() !== ReferenceArticle::DRAFT_STATUS;

            $articleFournisseur = new ArticleFournisseur();
            $articleFournisseur
                ->setFournisseur($fournisseur)
                ->setReference(trim($generatedReference))
                ->setReferenceArticle($referenceArticle)
                ->setLabel(trim($label))
                ->setVisible($isVisible);
        }
        else {
            throw new Exception(self::ERROR_REFERENCE_ALREADY_EXISTS);
        }

        $entityManager->persist($articleFournisseur);

        return $articleFournisseur;
    }

    public function findSimilarArticleFournisseur(ReferenceArticle $referenceArticle,
                                                  ?Fournisseur $fournisseur = null): ?ArticleFournisseur {
        $articleFournisseurRepository = $this->entityManager->getRepository(ArticleFournisseur::class);
        $articleFournisseursArray = empty($fournisseur)
            ? $articleFournisseurRepository->findByRefArticle($referenceArticle->getId())
            : $articleFournisseurRepository->findByRefArticleAndFournisseur($referenceArticle->getId(), $fournisseur->getId());
        $articleFournisseursCount = count($articleFournisseursArray);
        $indexArticleFournisseur = 0;
        $articleFournisseur = null;

        while(!isset($articleFournisseur)
            && $indexArticleFournisseur < $articleFournisseursCount) {

            /** @var ArticleFournisseur $currentArticleFournisseur */
            $currentArticleFournisseur = $articleFournisseursArray[$indexArticleFournisseur];
            if (($referenceArticle->getReference() === $currentArticleFournisseur->getReference())
                && ($referenceArticle->getLibelle() === $currentArticleFournisseur->getLabel())) {
                $articleFournisseur = $currentArticleFournisseur;
            }
            else {
                $indexArticleFournisseur++;
            }
        }
        return $articleFournisseur;
    }
}
