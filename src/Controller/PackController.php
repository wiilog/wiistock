<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Menu;
use App\Entity\Nature;
use App\Entity\Pack;

use App\Service\CSVExportService;
use App\Service\PackService;
use App\Service\UserService;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use DateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * @Route("/colis")
 */
class PackController extends AbstractController {

    /**
     * @Route("/", name="pack_index", options={"expose"=true})
     * @param EntityManagerInterface $entityManager
     * @param UserService $userService
     * @return RedirectResponse|Response
     */
    public function index(EntityManagerInterface $entityManager,
                          UserService $userService) {
        if (!$userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_PACK)) {
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
                        PackService $packService): Response {
        if ($request->isXmlHttpRequest()) {
            if (!$userService->hasRightFunction(Menu::TRACA, Action::DISPLAY_PACK)) {
                return $this->redirectToRoute('access_denied');
            }

            $data = $packService->getDataForDatatable($request->request);

            return new JsonResponse($data);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/csv", name="print_csv_packs", options={"expose"=true}, methods={"GET"})
     * @param Request $request
     * @param CSVExportService $CSVExportService
     * @param UserService $userService
     * @param TranslatorInterface $translator
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function printCSVPacks(Request $request,
                                  CSVExportService $CSVExportService,
                                  UserService $userService,
                                  TranslatorInterface $translator,
                                  EntityManagerInterface $entityManager): Response {

        if (!$userService->hasRightFunction(Menu::TRACA, Action::EXPORT)) {
            return $this->redirectToRoute('access_denied');
        }

        $dateMin = $request->query->get('dateMin');
        $dateMax = $request->query->get('dateMax');

        try {
            $dateTimeMin = DateTime::createFromFormat('Y-m-d H:i:s', $dateMin . ' 00:00:00');
            $dateTimeMax = DateTime::createFromFormat('Y-m-d H:i:s', $dateMax . ' 23:59:59');
        } catch (Throwable $throwable) {
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
            throw new BadRequestHttpException();
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
                    'comment' => $pack->getComment(),
                    'weight' => $pack->getWeight(),
                    'volume' => $pack->getVolume(),
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
                            UserService $userService): Response {
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
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="pack_edit", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param UserService $userService
     * @param PackService $packService
     * @param TranslatorInterface $translator
     * @return Response
     */
    public function edit(Request $request,
                         EntityManagerInterface $entityManager,
                         UserService $userService,
                         PackService $packService,
                         TranslatorInterface $translator): Response {
        if (!$userService->hasRightFunction(Menu::TRACA, Action::EDIT)) {
            return $this->redirectToRoute('access_denied');
        }
        $data = json_decode($request->getContent(), true);
        $response = [];
        $packRepository = $entityManager->getRepository(Pack::class);
        $natureRepository = $entityManager->getRepository(Nature::class);

        $pack = $packRepository->find($data['id']);
        $packDataIsValid =  $packService->checkPackDataBeforeEdition($data);
        if (!empty($pack) && $packDataIsValid['success']) {
            $packService
                ->editPack($data, $natureRepository, $pack);

            $entityManager->flush();
            $response = [
                'success' => true,
                'msg' => $translator->trans('colis.Le colis {numéro} a bien été modifié', [
                        "{numéro}" => '<strong>' . $pack->getCode() . '</strong>'
                    ]) . '.'
            ];
        } else if (!$packDataIsValid['success']) {
            $response = $packDataIsValid;
        }
        return new JsonResponse($response);
    }

    /**
     * @Route("/supprimer", name="pack_delete", options={"expose"=true}, methods="GET|POST")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param UserService $userService
     * @param TranslatorInterface $translator
     * @return Response
     */
    public function delete(Request $request, EntityManagerInterface $entityManager, UserService $userService, TranslatorInterface $translator): Response
    {
        if ($request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$userService->hasRightFunction(Menu::TRACA, Action::DELETE)) {
                return $this->redirectToRoute('access_denied');
            }
            $packRepository = $entityManager->getRepository(Pack::class);

            $pack = $packRepository->find($data['pack']);
            $packCode = $pack->getCode();

            if(!$pack->getTrackingMovements()->isEmpty()) {
                $msg = $translator->trans("colis.Ce colis est référencé dans un ou plusieurs mouvements de traçabilité");
            }

            if(!$pack->getDispatchPacks()->isEmpty()) {
                $msg = $translator->trans("colis.Ce colis est référencé dans un ou plusieurs acheminements");
            }

            if(!$pack->getLitiges()->isEmpty()) {
                $msg = $translator->trans("colis.Ce colis est référencé dans un ou plusieurs litiges");
            }

            if($pack->getArrivage()) {
                $msg = $translator->trans('colis.Ce colis est utilisé dans l\'arrivage {arrivage}', [
                    "{arrivage}" => $pack->getArrivage()->getNumeroArrivage()
                ]);
            }

            if(isset($msg)) {
                return $this->json([
                    "success" => false,
                    "msg" => $msg
                ]);
            }

            $entityManager->remove($pack);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'msg' => $translator->trans('colis.Le colis {numéro} a bien été supprimé', [
                        "{numéro}" => '<strong>' . $packCode . '</strong>'
                    ]) . '.'
            ]);
        }

        throw new BadRequestHttpException();
    }

}
