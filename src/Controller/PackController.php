<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Arrivage;
use App\Entity\CategoryType;
use App\Entity\ChampLibre;
use App\Entity\Chauffeur;
use App\Entity\FieldsParam;
use App\Entity\Fournisseur;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\Pack;

use App\Entity\ParametrageGlobal;
use App\Entity\PieceJointe;
use App\Entity\Statut;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Service\CSVExportService;
use App\Service\PackService;
use App\Service\SpecificService;
use App\Service\StatutService;
use App\Service\UserService;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use DateTime;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route("/colis")
 */
class PackController extends AbstractController
{

    /**
     * @Route("/", name="pack_index", options={"expose"=true})
     * @param EntityManagerInterface $entityManager
     * @param UserService $userService
     * @return RedirectResponse|Response
     */
    public function index(EntityManagerInterface $entityManager,
                          UserService $userService)
    {
        if (!$userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_MOUV)) {
            return $this->redirectToRoute('access_denied');
        }

        $naturesRepository = $entityManager->getRepository(Nature::class);

        return $this->render('pack/index.html.twig', [
            'natures' => $naturesRepository->findAll()
        ]);
    }

    /**
     * @Route("/api", name="pack_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param UserService $userService
     * @param PackService $packService
     * @return Response
     * @throws Exception
     */
    public function api(Request $request,
                        UserService $userService,
                        PackService $packService): Response
    {
        if ($request->isXmlHttpRequest()) {
            if (!$userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_MOUV)) {
                return $this->redirectToRoute('access_denied');
            }

            $data = $packService->getDataForDatatable($request->request);

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/csv", name="pack_export_csv", options={"expose"=true}, methods={"GET"})
     * @param Request $request
     * @param CSVExportService $CSVExportService
     * @param TranslatorInterface $translator
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function getPackCsv(Request $request,
                                         CSVExportService $CSVExportService,
                                         TranslatorInterface $translator,
                                         EntityManagerInterface $entityManager): Response
    {
        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch (\Throwable $throwable) {
        }

        if (isset($dateTimeMin) && isset($dateTimeMax)) {
            $packRepository = $entityManager->getRepository(Pack::class);

            $packs = $packRepository->getByDates($dateTimeMin, $dateTimeMax);

            $csvHeader = [
                'Numéro colis',
                $translator->trans('natures.Nature de colis'),
                'Date du dernier mouvement',
                'Issu de',
                'Emplacement',
            ];

            return $CSVExportService->createBinaryResponseFromData(
                'export_packs.csv',
                $packs,
                $csvHeader,
                function (Pack $pack) use ($translator) {
                    $lastPackMovement = $pack->getLastTracking();
                    $row = [];
                    $row[] = $pack->getCode();
                    $row[] = $pack->getNature() ? $pack->getNature()->getLabel() : '';
                    $row[] = $lastPackMovement
                        ? ($lastPackMovement->getDatetime()
                            ? $lastPackMovement->getDatetime()->format('d/m/Y \à H:i:s')
                            : '')
                        : '';
                    $row[] = $pack->getArrivage() ? $translator->trans('arrivage.arrivage') : '-';
                    $row[] = $lastPackMovement
                        ? ($lastPackMovement->getEmplacement()
                            ? $lastPackMovement->getEmplacement()->getLabel()
                            : '')
                        : '';
                    return [$row];
                }
            );
        } else {
            throw new NotFoundHttpException('404');
        }
    }

    /**
     * @Route("/{packCode}", name="get_pack_intel", options={"expose"=true}, methods={"GET"}, condition="request.isXmlHttpRequest()")
     * @param EntityManagerInterface $entityManager
     * @param string $packCode
     * @return JsonResponse
     */
    public function getPackIntel(EntityManagerInterface $entityManager,
                                 string $packCode): JsonResponse {
        $packRepository = $entityManager->getRepository(Pack::class);
        $pack = $packRepository->findOneBy(['code' => $packCode]);
        return new JsonResponse([
            'success' => !empty($pack),
            'pack' => !empty($pack)
                ? [
                    'code' => $packCode,
                    'quantity' => $pack->getQuantity(),
                    'nature' => $pack->getNature()
                        ? [
                            'id' => $pack->getNature()->getId(),
                            'label' => $pack->getNature()->getLabel()
                        ]
                        : null
                ]
                : null
        ]);
    }

    /**
     * @Route("/api-modifier", name="pack_edit_api", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param UserService $userService
     * @return Response
     */
    public function editApi(Request $request,
                            EntityManagerInterface $entityManager,
                            UserService $userService): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if ($userService->hasRightFunction(Menu::TRACA, Action::EDIT)) {
                $packRepository = $entityManager->getRepository(Pack::class);
                $natureRepository = $entityManager->getRepository(Nature::class);
                $pack = $packRepository->find($data['id']);
                $html = $this->renderView('pack/modalEditPackContent.html.twig', [
                    'natures' => $natureRepository->findAll(),
                    'pack' => $pack
                ]);
            } else {
                $html = '';
            }

            return new JsonResponse($html);
        }
        throw new NotFoundHttpException('404');
    }

    /**
     * @Route("/modifier", name="pack_edit", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param UserService $userService
     * @return Response
     */
    public function edit(Request $request,
                         EntityManagerInterface $entityManager,
                         UserService $userService): Response
    {
        if (!$userService->hasRightFunction(Menu::TRACA, Action::EDIT)) {
            return $this->redirectToRoute('access_denied');
        }
        $data = json_decode($request->getContent(), true);

        $packRepository = $entityManager->getRepository(Pack::class);
        $natureRepository = $entityManager->getRepository(Nature::class);

        $pack = $packRepository->find($data['id']);

        if (!empty($pack)) {
            $natureId = $data['nature'];
            $quantity = $data['quantity'];

            if ($quantity < 1) {
                return new JsonResponse([
                    'success' => false,
                    'msg' => 'La quantité doit être supérieure à 0.'
                ]);
            }

            if (!empty($natureId)) {
                $nature = $natureRepository->find($natureId);
                if (!empty($nature)) {
                    $pack->setNature($nature);
                }
            }

            if (!empty($quantity)) {
                $pack->setQuantity($quantity);
            }

            $entityManager->flush();
        }

        return new JsonResponse([
            'success' => true,
            'msg' => 'Votre colis a bien été modifié.'
        ]);
    }
}
