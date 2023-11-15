<?php


namespace App\Service;


use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Pack;
use App\Entity\ReceptionLine;
use App\Entity\Reception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig_Environment;
use WiiCommon\Helper\Stream;


class ReceptionLineService {

    #[Required]
    public Twig_Environment $templating;

    #[Required]
    public UserService $userService;

    public function persistReceptionLine(EntityManagerInterface $entityManager,
                                         Reception              $reception,
                                         ?Pack                  $pack): ReceptionLine {

        $line = new ReceptionLine();
        $line
            ->setReception($reception)
            ->setPack($pack);
        $entityManager->persist($line);
        return $line;
    }

    public function getDataForDatatable(EntityManagerInterface $entityManager,
                                        InputBag $params,
                                        Reception $reception){
        $receptionLineRepository = $entityManager->getRepository(ReceptionLine::class);
        $receptionsLines = $receptionLineRepository->getByReception($reception, $params);

        $rows = [];
        foreach ($receptionsLines['data'] as $receptionLine) {
            $rows[] = $this->dataRowReceptionLine($receptionLine, $reception);
        }

        return [
            'data' => $rows,
            'recordsTotal' => $receptionsLines['total'],
            'recordsFiltered' => $receptionsLines['count'],
        ];
    }

    public function dataRowReceptionLine(array $line, Reception $reception): array {
        $hasRight = $reception-> getStatut()->getCode() !== Reception::STATUT_RECEPTION_TOTALE
            && $this->userService->hasRightFunction(Menu::ORDRE, Action::EDIT);

        return [
            'actions' => $this->templating->render('utils/action-buttons/dropdown.html.twig', [
                    'actions' => [
                        [
                            'hasRight' => $hasRight,
                            'title' => 'Modifier',
                            'icon' => 'fa fa-edit',
                            'actionOnClick' => true,
                            'attributes' => [
                                'onclick' => "editReceptionLine(" . $line['id'] . ")",
                            ]
                        ],
                        [
                            'hasRight' => $hasRight,
                            'title' => 'Supprimer',
                            'icon' => 'wii-icon wii-icon-trash-black',
                            'attributes' => [
                                "onclick" => "deleteReceptionLine(" . $line['id'] . ")",
                            ]
                        ],
                    ],
                ]),
            'logo' => $line['logo']
                ? "<img src='{$line['logo']}' alt='Logo du type' style='height: 20px; width: 20px;'>"
                : '',
            'reference' => $line['reference'],
            'label' => $line['label'],
            'packCode' => $line['packCode'],
            'articles' => $line['articles'],
            'emergency' => $line['emergency'],
        ];
    }
}
