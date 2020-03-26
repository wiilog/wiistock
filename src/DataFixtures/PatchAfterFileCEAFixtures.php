<?php

namespace App\DataFixtures;

use App\Entity\CategoryType;
use App\Entity\Type;
use App\Entity\ValeurChampLibre;

use App\Repository\ArticleFournisseurRepository;
use App\Repository\ArticleRepository;
use App\Repository\CategorieCLRepository;
use App\Repository\DemandeRepository;
use App\Repository\EmplacementRepository;
use App\Repository\FournisseurRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\ValeurChampLibreRepository;
use App\Repository\ChampLibreRepository;

use Doctrine\Bundle\FixturesBundle\Fixture;

use Doctrine\Common\Persistence\ObjectManager;

use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;


class PatchAfterFileCEAFixtures extends Fixture
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
     * @var ArticleFournisseurRepository
     */
    private $articleFournisseurRepository;

    /**
     * @var ValeurChampLibreRepository
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

    public function __construct(ArticleRepository $articleRepository, DemandeRepository $demandeRepository, ValeurChampLibreRepository $valeurChampLibreRepository, ArticleFournisseurRepository $articleFournisseurRepository, EmplacementRepository $emplacementRepository, CategorieCLRepository $categorieCLRepository, ReferenceArticleRepository $refArticleRepository, UserPasswordEncoderInterface $encoder, ChampLibreRepository $champsLibreRepository)
    {
        $this->articleRepository = $articleRepository;
        $this->demandeRepository = $demandeRepository;
        $this->champLibreRepository = $champsLibreRepository;
        $this->encoder = $encoder;
        $this->refArticleRepository = $refArticleRepository;
        $this->categorieCLRepository = $categorieCLRepository;
        $this->emplacementRepository = $emplacementRepository;
        $this->articleFournisseurRepository = $articleFournisseurRepository;
        $this->valeurChampLibreRepository = $valeurChampLibreRepository;
    }

    public function load(ObjectManager $manager)
    {
        $clCodeProjet = $this->champLibreRepository->findOneByLabel('code projet');
        $clDestinataire = $this->champLibreRepository->findOneByLabel('destinataire');
        $typeRepository = $manager->getRepository(Type::class);
        $siliTypeDemande = $typeRepository->findOneByCategoryLabelAndLabel(CategoryType::DEMANDE_LIVRAISON, 'sili');
        $path = "src/DataFixtures/demandes.csv";
        $file = fopen($path, "r");

        $rows = [];
        while (($data = fgetcsv($file, 1000, ";")) !== false) {
            $rows[] = $data;
        }

        array_shift($rows); // supprime la 1è ligne d'en-têtes
        foreach ($rows as $row) {
            if (empty($row[0])) continue;
            $demande = $this->demandeRepository->find($row[0]);
            if ($demande->getType() !== $siliTypeDemande) $demande->setType($siliTypeDemande);
            $valeurChampLibreCP = $this->valeurChampLibreRepository->findOneByDemandeLivraisonAndChampLibre($demande, $clCodeProjet);
            $valeurChampLibreDestinataire = $this->valeurChampLibreRepository->findOneByDemandeLivraisonAndChampLibre($demande, $clDestinataire);
            if (empty($valeurChampLibreCP)) {
                $valeurChampLibreCP = new ValeurChampLibre();
                $manager->persist($valeurChampLibreCP);
            }
			$valeurChampLibreCP
				->setValeur($row[1])
				->addDemandesLivraison($demande)
				->setChampLibre($clCodeProjet);

            if (empty($valeurChampLibreDestinataire)) {
                $valeurChampLibreDestinataire = new ValeurChampLibre();
                $manager->persist($valeurChampLibreDestinataire);
            }
			$valeurChampLibreDestinataire
				->setValeur($row[2])
				->addDemandesLivraison($demande)
				->setChampLibre($clDestinataire);

            dump('Setted cp and desti CL ' . $row[1] . ' - ' . $row[2] . ' for demande n°' . $demande->getId());
        }
        $manager->flush();
    }

}
