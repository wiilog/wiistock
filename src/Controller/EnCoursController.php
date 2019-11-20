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
        return $this->render('en_cours/index.html.twig');
    }

    /**
     * @Route("/encours/api", name="en_cours_api", options={"expose"=true}, methods="GET|POST")
     */
    public function api(): Response
    {
        $references = [];
        foreach ($this->emplacementRepository->findWhereArticleIs() as $emplacement) {
            foreach ($this->mouvementStockRepository->findByEmplacementTo($emplacement) as $mvt) {
                if (intval($this->mouvementStockRepository->findByEmplacementToAndArticleAndDate($emplacement, $mvt)) === 0) {
                    $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
                    $dateMvt = new \DateTime($mvt->getDate()->format('YmdHis'), new \DateTimeZone('Europe/Paris'));
                    $diff = $date->diff($dateMvt);
                    $heureEmplacement = $emplacement->getDateMaxTime() ? intval(explode(':',$emplacement->getDateMaxTime())[0]) : null;
                    $minuteEmplacement = $heureEmplacement ? intval(explode(':',$emplacement->getDateMaxTime())[1]) : null;
                    $retard = true;
                    if ($heureEmplacement > $diff->h) $retard = false;
                    if ($heureEmplacement === $diff->h) $retard = !($minuteEmplacement > $diff->i);
                    $references[] = [
                        'Référence' => ($mvt->getRefArticle() ? $mvt->getRefArticle()->getReference() : $mvt->getArticle()->getReference()),
                        'Emplacement' => ($emplacement->getLabel()),
                        'Durée' => ($date->diff($dateMvt)->h < 10 ? '0' . $date->diff($dateMvt)->h : $date->diff($dateMvt)->h)  . ':' . ($date->diff($dateMvt)->i < 10 ? '0' . $date->diff($dateMvt)->i : $date->diff($dateMvt)->i),
                        'Retard' => $retard ? 'Retard' : 'Normal',
                    ];
                }
            }
        }

        return new JsonResponse([
            'data' => $references
        ]);
    }
}