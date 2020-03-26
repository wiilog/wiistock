<?php

namespace App\DataFixtures;

use App\Entity\Article;
use App\Entity\ArticleFournisseur;
use App\Entity\CategorieStatut;
use App\Entity\Emplacement;
use App\Entity\Fournisseur;
use App\Entity\Statut;
use App\Entity\Type;
use App\Repository\ArticleFournisseurRepository;
use App\Repository\CategorieCLRepository;
use App\Repository\EmplacementRepository;
use App\Repository\FournisseurRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\ValeurChampLibreRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Entity\ReferenceArticle;
use App\Repository\ChampLibreRepository;

class PatchRefArticleSILIArticleFixtures extends Fixture implements FixtureGroupInterface
{
    private $encoder;

    /**
     * @var ChampLibreRepository
     */
    private $champLibreRepository;

    /**
     * @var ReferenceArticleRepository
     */
    private $refArticleRepository;

    /**
     * @var CategorieCLRepository
     */
    private $categorieCLRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * @var FournisseurRepository
     */
    private $fournisseurRepository;

    /**
     * @var ArticleFournisseurRepository
     */
    private $articleFournisseurRepository;

    /**
     * @var ValeurChampLibreRepository
     */
    private $valeurChampLibreRepository;

    public function __construct(ValeurChampLibreRepository $valeurChampLibreRepository, ArticleFournisseurRepository $articleFournisseurRepository, FournisseurRepository $fournisseurRepository, EmplacementRepository $emplacementRepository, CategorieCLRepository $categorieCLRepository, ReferenceArticleRepository $refArticleRepository, UserPasswordEncoderInterface $encoder, ChampLibreRepository $champLibreRepository)
    {
        $this->champLibreRepository = $champLibreRepository;
        $this->encoder = $encoder;
        $this->refArticleRepository = $refArticleRepository;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->articleFournisseurRepository = $articleFournisseurRepository;
        $this->valeurChampLibreRepository = $valeurChampLibreRepository;
    }

    public function load(ObjectManager $manager)
    {
        $typeRepository = $manager->getRepository(Type::class);
        $statutRepository = $manager->getRepository(Statut::class);

        $type = $typeRepository->findOneByLabel(Type::LABEL_SILI);
        $articlesSili = $this->refArticleRepository->findBy(['type' => $type]);

        dump(count($articlesSili) . ' articles à créer.');

        $i = 0;
        foreach ($articlesSili as $refCEA) {

            $article = new Article();
            $manager->persist($article);

            $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
            $ref = $date->format('YmdHis');
            $statut = $statutRepository->findOneByCategorieNameAndStatutCode(CategorieStatut::ARTICLE, Article::STATUT_ACTIF);

            $article
                ->setStatut($statut)
                ->setLabel($refCEA->getLibelle())
                ->setQuantite($refCEA->getQuantiteStock())
                ->setCommentaire($refCEA->getCommentaire())
                ->setConform(true)
                ->setReference($ref . '-1')
                ->setType($refCEA->getType());

            // transfert du champ libre Adresse (SILI) vers le champ emplacement
            $champLibreAdresse = $this->champLibreRepository->findBy(['label' => 'Adresse (SILI)']);
            if (empty($champLibreAdresse)) {
                dump('champ libre adresse SILI non retrouvé en base');
                exit;
            }

            $valeurCL = $this->valeurChampLibreRepository->findOneByRefArticleAndChampLibre($refCEA->getId(), $champLibreAdresse);
            if (!empty($valeurCL)) {
                $emplacement = $this->emplacementRepository->findOneBy(['label' => $valeurCL->getValeur()]);

                if (empty($emplacement)) {
                    $emplacement = new Emplacement();
                    $emplacement->setLabel($valeurCL);
                    $manager->persist($emplacement);
                }
                $article->setEmplacement($emplacement);
            }

            // article fournisseur
            $fournisseur = $this->initFournisseur($manager);
            $articleFournisseur = new ArticleFournisseur();
            $articleFournisseur
                ->setLabel($refCEA->getLibelle())
                ->setReference(time() . '-' . $i)// code aléatoire unique
                ->setFournisseur($fournisseur)
                ->setReferenceArticle($refCEA);
            $manager->persist($articleFournisseur);

            $article->setArticleFournisseur($articleFournisseur);

            // on met à jour l'article de référence
            $refCEA
                ->setQuantiteStock(null)
                ->setTypeQuantite(ReferenceArticle::TYPE_QUANTITE_ARTICLE)
                ->setEmplacement(null);

            $i++;
            dump($i . ' : article créé pour ref CEA ' . $refCEA->getReference());
            $manager->flush();
        }

    }

    public static function getGroups(): array
    {
        return ['SILIarticle'];
    }

    /**
     * @param ObjectManager $manager
     * @return Fournisseur
     */
    public function initFournisseur(ObjectManager $manager): Fournisseur
    {
        $fournisseurLabel = 'A DETERMINER';
        $fournisseurRef = 'A_DETERMINER';
        $fournisseur = $this->fournisseurRepository->findOneBy(['codeReference' => $fournisseurRef]);

        // si le fournisseur n'existe pas, on le crée
        if (empty($fournisseur)) {
            $fournisseur = new Fournisseur();
            $fournisseur
                ->setNom($fournisseurLabel)
                ->setCodeReference($fournisseurRef);
            $manager->persist($fournisseur);
            $manager->flush();
        }
        return $fournisseur;
    }

}
