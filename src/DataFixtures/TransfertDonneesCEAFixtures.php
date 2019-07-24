<?php

namespace App\DataFixtures;

use App\Entity\CategoryType;
use App\Entity\Type;

use App\Repository\TypeRepository;
use App\Repository\ChampsLibreRepository;
use App\Repository\ArticleFournisseurRepository;
use App\Repository\ArticleRepository;
use App\Repository\CategorieCLRepository;
use App\Repository\DemandeRepository;
use App\Repository\EmplacementRepository;
use App\Repository\FournisseurRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\StatutRepository;
use App\Repository\ValeurChampsLibreRepository;

use Doctrine\Bundle\FixturesBundle\Fixture;

use Doctrine\Common\Persistence\ObjectManager;

use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;


class TransfertDonneesCEAFixtures extends Fixture
{
    private $encoder;


    /**
     * @var TypeRepository
     */
    private $typeRepository;
    /**
     * @var ChampsLibreRepository
     */
    private $champsLibreRepository;

    /**
     * @var StatutRepository
     */
    private $statutRepository;

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
     * @var ValeurChampsLibreRepository
     */
    private $valeurChampLibreRepository;

    /**
     * @var DemandeRepository
     */
    private $demandeRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    public function __construct(ArticleRepository $articleRepository, DemandeRepository $demandeRepository, ValeurChampsLibreRepository $valeurChampLibreRepository, ArticleFournisseurRepository $articleFournisseurRepository, FournisseurRepository $fournisseurRepository, EmplacementRepository $emplacementRepository, CategorieCLRepository $categorieCLRepository, ReferenceArticleRepository $refArticleRepository, UserPasswordEncoderInterface $encoder, TypeRepository $typeRepository, ChampsLibreRepository $champsLibreRepository, StatutRepository $statutRepository)
    {
        $this->articleRepository = $articleRepository;
        $this->demandeRepository = $demandeRepository;
        $this->typeRepository = $typeRepository;
        $this->champsLibreRepository = $champsLibreRepository;
        $this->encoder = $encoder;
        $this->statutRepository = $statutRepository;
        $this->refArticleRepository = $refArticleRepository;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->fournisseurRepository = $fournisseurRepository;
        $this->articleFournisseurRepository = $articleFournisseurRepository;
        $this->valeurChampLibreRepository = $valeurChampLibreRepository;
    }

    public function load(ObjectManager $manager)
    {
        $siliType = $this->typeRepository->findOneByCategoryLabelAndLabel(CategoryType::ARTICLE, Type::LABEL_SILI);
        $clCodeProjet = $this->champsLibreRepository->findOneByLabel('code projet');
        $clDestinataire = $this->champsLibreRepository->findOneByLabel('destinataire');
        $toPut = 'demande' . ';' . 'numéro' . ';' . 'codeProjet' . ';' . 'destinataire' . PHP_EOL;
        $path = "src/DataFixtures/demandes.csv";
        file_put_contents($path, $toPut);

        foreach ($this->demandeRepository->findAll() as $demande) {
            $articles = $this->articleRepository->getByDemandeAndType($demande, $siliType);
            if (count($articles) > 0) {

                $article = $articles[0];
                $valeurCodeProjet = $this->valeurChampLibreRepository->findOneByChampLibreAndArticle($clCodeProjet->getId(), $article->getId())->getValeur();
                $valeurDestinataire = $this->valeurChampLibreRepository->findOneByChampLibreAndArticle($clDestinataire->getId(), $article->getId())->getValeur();

                $specialChars = ['├®', '├¿', '├º', '├á', '├½', '├ë'];
                $normalChars = ['é', 'è', 'ç', 'à', 'ë', 'É'];
                $valeurDestinataire = str_replace($specialChars, $normalChars, $valeurDestinataire);
                $toPut = $demande->getId() . ';' . $demande->getNumero() . ';' . $valeurCodeProjet . ';' . $valeurDestinataire . PHP_EOL;
                dump($toPut);
                file_put_contents($path, $toPut, FILE_APPEND | LOCK_EX);
            }
        }
    }

}
