<?php


namespace App\Service;

use App\Entity\ArticleFournisseur;
use App\Entity\Fournisseur;
use App\Entity\ReferenceArticle;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Component\HttpFoundation\Request;


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
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function createArticleFournisseur(array $data, bool $generateReference = false): ArticleFournisseur
    {
        $articleFournisseurRepository = $this->entityManager->getRepository(ArticleFournisseur::class);
        $fournisseurRepository = $this->entityManager->getRepository(Fournisseur::class);
        $referenceArticleRepository = $this->entityManager->getRepository(ReferenceArticle::class);

        $fournisseur = ($data['fournisseur'] instanceof Fournisseur)
            ? $data['fournisseur']
            : $fournisseurRepository->find(intval($data['fournisseur']));

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

            $articleFournisseur = new ArticleFournisseur();
            $articleFournisseur
                ->setFournisseur($fournisseur)
                ->setReference($generatedReference)
                ->setReferenceArticle($referenceArticle)
                ->setLabel($label);
        }
        else {
            throw new \Exception(self::ERROR_REFERENCE_ALREADY_EXISTS);
        }
        return $articleFournisseur;
    }
}
