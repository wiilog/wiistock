<?php


namespace App\Controller;

use phpDocumentor\Reflection\Types\Integer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\EmplacementRepository;
use App\Repository\MouvementStockRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class EnCoursController extends AbstractController
{

    /**
     * @var MouvementStockRepository
     */
    private $mouvementStockRepository;

    /**
     * @var EmplacementRepository
     */
    private $emplacementRepository;

    /**
     * EnCoursController constructor.
     * @param MouvementStockRepository $mouvementStockRepository
     * @param EmplacementRepository $emplacementRepository
     */
    public function __construct(MouvementStockRepository $mouvementStockRepository, EmplacementRepository $emplacementRepository)
    {
        $this->mouvementStockRepository = $mouvementStockRepository;
        $this->emplacementRepository = $emplacementRepository;
    }


    /**
     * @Route("/encours", name="en_cours", methods={"GET"})
     */
    public function index(): Response
    {
        return $this->render('en_cours/index.html.twig', [
            'emplacements' => $this->api()
        ]);
    }

    /**
     * @Route("/encours/api", name="en_cours_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(): array
    {
        $emplacements = [];
        foreach ($this->emplacementRepository->findWhereArticleIs() as $emplacement) {
            foreach ($this->mouvementStockRepository->findByEmplacementTo($emplacement) as $mvt) {
                if (intval($this->mouvementStockRepository->findByEmplacementToAndArticleAndDate($emplacement, $mvt)) === 0) {
                    $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
                    $dateMvt = new \DateTime($mvt->getDate()->format('YmdHis'), new \DateTimeZone('Europe/Paris'));
                    $diff = $date->diff($dateMvt);
                    $heureEmplacement = $emplacement->getDateMaxTime() ? intval(explode(':',$emplacement->getDateMaxTime())[0]) : null;
                    $minuteEmplacement = $heureEmplacement ? intval(explode(':',$emplacement->getDateMaxTime())[1]) : null;
                    $retard = true;
                    $diffHours = $diff->h + ($diff->d*24);
                    if ($heureEmplacement > $diffHours) $retard = false;
                    if ($heureEmplacement === $diffHours) $retard = !($minuteEmplacement > $diff->i);
                    $diffString =
                        ($diffHours < 10 ? '0' . $diffHours : $diffHours)
                        . ':' . ($date->diff($dateMvt)->i < 10 ? '0' . $date->diff($dateMvt)->i : $date->diff($dateMvt)->i);
                    $emplacements[$emplacement->getLabel()][] = [
                        'ref' => ($mvt->getRefArticle() ? $mvt->getRefArticle()->getReference() : $mvt->getArticle()->getReference()),
                        'time' => $diffString,
                        'late' => $retard
                    ];
                }
            }
        }
        return $emplacements;
    }
}