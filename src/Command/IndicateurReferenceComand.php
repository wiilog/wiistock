<?php


namespace App\Command;


use App\Entity\Article;
use App\Entity\FiabilityByReference;
use App\Entity\MouvementStock;
use App\Entity\ReferenceArticle;
use App\Repository\FiabilityByReferenceRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\EntityManagerInterface;


class IndicateurReferenceComand extends Command
{
    /**
     * @var entityManagerInterface
     */
    private $entityManager;

    /**
     * @var fiabilityByReferenceRepository
     */
    private $fiabilityByReferenceRepository;


    public function __construct(FiabilityByReferenceRepository $fiabilityByReferenceRepository,
                                EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->fiabilityByReferenceRepository = $fiabilityByReferenceRepository;
    }

    protected function configure()
    {
        $this->setName('app:indicateur-reference');

        $this->setDescription('Enregistre l\'indicateur de reference du mois courant');

    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->entityManager;
        $referenceArticleRepository = $em->getRepository(ReferenceArticle::class);
        $articleRepository = $em->getRepository(Article::class);
        $mouvementStockRepository = $em->getRepository(MouvementStock::class);

        $types = [
            MouvementStock::TYPE_INVENTAIRE_ENTREE,
            MouvementStock::TYPE_INVENTAIRE_SORTIE
        ];

        $firstDayOfLastMonth = date("Y-m-d", strtotime("first day of last month"));
        $lastDayOfThisMonth = date("Y-m-d", strtotime("first day of this month"));

        $nbStockInventoryMouvementsOfThisMonth = $mouvementStockRepository->countByTypes($types, $firstDayOfLastMonth, $lastDayOfThisMonth);
        $nbActiveRefAndArtOfThisMonth = $referenceArticleRepository->countActiveTypeRefRef() + $articleRepository->countActiveArticles();
        if ($nbActiveRefAndArtOfThisMonth > 0) {
        	$nbrFiabiliteReferenceOfLastMonth = (1 - ($nbStockInventoryMouvementsOfThisMonth / $nbActiveRefAndArtOfThisMonth)) * 100;
        	round($nbrFiabiliteReferenceOfLastMonth);
		}

        $dateDebut = new \DateTime($firstDayOfLastMonth);
        $fiabilityReference = new FiabilityByReference();
        $fiabilityReference
            ->setDate($dateDebut)
            ->setIndicateur($nbrFiabiliteReferenceOfLastMonth ?? null);

        $em->persist($fiabilityReference);
        $em->flush();
    }
}
