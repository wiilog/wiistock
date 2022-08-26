<?php

namespace App\Controller\IOT;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\CategorieCL;
use App\Entity\CategoryType;
use App\Entity\FreeField;
use App\Entity\IOT\Pairing;
use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorWrapper;
use App\Entity\Menu;
use App\Entity\Type;
use App\Entity\Utilisateur;
use App\Helper\PostHelper;

use App\Service\FreeFieldService;
use App\Service\IOT\PairingService;
use App\Service\IOT\SensorWrapperService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use WiiCommon\Helper\Stream;


/**
 * @Route("/iot/capteur")
 */
class SensorWrapperController extends AbstractController
{
    /**
     * @Route("/liste", name="sensor_wrapper_index")
     * @HasPermission({Menu::IOT, Action::DISPLAY_SENSOR})
     */
    public function index(EntityManagerInterface $entityManager): Response {
        $freeFieldsRepository = $entityManager->getRepository(FreeField::class);

        $types = $entityManager->getRepository(Type::class)->findByCategoryLabels([CategoryType::SENSOR]);

        return $this->render('IOT/sensor_wrapper/index.html.twig', [
            'freeFieldsTypes' => Stream::from($types)->map(function (Type $type) use ($freeFieldsRepository) {
                $freeFields = $freeFieldsRepository->findByTypeAndCategorieCLLabel($type, CategorieCL::SENSOR);

                return [
                    'typeLabel' => $type->getLabel(),
                    'typeId' => $type->getId(),
                    'freeFields' => $freeFields,
                ];
            })
        ]);
    }

    /**
     * @Route("/api", name="sensor_wrapper_api", options={"expose"=true}, methods={"POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::IOT, Action::DISPLAY_SENSOR})
     */
    public function api(Request $request,
                        SensorWrapperService $sensorWrapperService): Response {
        $data = $sensorWrapperService->getDataForDatatable($request->request);
        return $this->json($data);
    }

    /**
     * @Route("/supprimer", name="sensor_wrapper_delete", options={"expose"=true}, methods={"GET", "POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::IOT, Action::DELETE})
     */
    public function delete(Request $request, EntityManagerInterface $entityManager): Response {

        if($data = json_decode($request->getContent(), true)) {
            $sensorWrapperRepository = $entityManager->getRepository(SensorWrapper::class);
            $sensorWrapper = $sensorWrapperRepository->find($data['id']);

            $name = $sensorWrapper->getName();

            $sensorWrapper->setDeleted(true);
            $activePairings = $sensorWrapper->getPairings()->filter(fn(Pairing $pairing) => $pairing->isActive());
            foreach ($activePairings as $pairing) {
                $pairing
                    ->setActive(false)
                    ->setEnd(new DateTime('now'));
            }
            foreach ($sensorWrapper->getTriggerActions() as $action) {
                $entityManager->remove($action);
            }
            $entityManager->flush();

            return $this->json([
                'success' => true,
                'msg' => "Le capteur <strong>${name}</strong> a bien été supprimé"
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/creer", name="sensor_wrapper_new", options={"expose"=true}, methods={"GET", "POST"})
     * @HasPermission({Menu::IOT, Action::CREATE})
     */
    public function new(Request $request,
                        EntityManagerInterface $entityManager,
                        FreeFieldService $freeFieldService): Response
    {

        $post = json_decode($request->getContent(), true);

        $name = PostHelper::string($post, 'name');
        $manager = PostHelper::entity($entityManager, $post, 'manager', Utilisateur::class);

        /** @var Sensor|null $sensor */
        $sensor = PostHelper::entity($entityManager, $post, 'sensor', Sensor::class);

        if (empty($sensor)) {
            return $this->json([
                'success' => false,
                'msg' => "Le capteur sélectionné n'est pas valide."
            ]);
        }
        else if ($sensor->getAvailableSensorWrapper()) {
            return $this->json([
                'success' => false,
                'msg' => "Le capteur est déjà approvisionné."
            ]);
        }

        if (empty($name)) {
            return $this->json([
                'success' => false,
                'msg' => "Le nom du capteur doit être saisi."
            ]);
        }

        $sensorWrapper = new SensorWrapper();
        $sensorWrapper
            ->setName($name)
            ->setManager($manager)
            ->setSensor($sensor);

        $freeFieldService->manageFreeFields($sensorWrapper, $post, $entityManager);

        $entityManager->persist($sensorWrapper);
        $entityManager->flush();

        $name = $sensorWrapper->getName();

        return $this->json([
            'success' => true,
            'msg' => "Le capteur <strong>${name}</strong> a bien été créé"
        ]);
    }

    /**
     * @Route("/api-modifier", name="sensor_wrapper_edit_api", options={"expose"=true},  methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::IOT, Action::EDIT})
     */
    public function editApi(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $sensorWrapper = $entityManager->getRepository(SensorWrapper::class)->find($data['id']);

            $json = $this->renderView('IOT/sensor_wrapper/edit_content.html.twig', [
                'sensorWrapper' => $sensorWrapper,
            ]);

            return $this->json($json);
        }
        throw new BadRequestHttpException();
    }

    /**
     * @Route("/modifier", name="sensor_wrapper_edit", options={"expose"=true}, methods={"GET", "POST"})
     * @HasPermission({Menu::IOT, Action::EDIT})
     */
    public function edit(EntityManagerInterface $entityManager,
                         Request $request,
                         FreeFieldService $freeFieldService): Response {

        $post = json_decode($request->getContent(), true);

        $name = PostHelper::string($post, 'name');
        $manager = PostHelper::entity($entityManager, $post, 'manager', Utilisateur::class);

        /** @var SensorWrapper|null $sensorWrapper */
        $sensorWrapper = PostHelper::entity($entityManager, $post, 'id', SensorWrapper::class);

        if (empty($sensorWrapper) || $sensorWrapper->isDeleted()) {
            return $this->json([
                'success' => false,
                'msg' => "Cet approvisionnement de capteur n'existe plus."
            ]);
        }

        if (empty($name)) {
            return $this->json([
                'success' => false,
                'msg' => "Le nom du capteur doit être saisi."
            ]);
        }

        $sensorWrapper
            ->setName($name)
            ->setManager($manager);

        $freeFieldService->manageFreeFields($sensorWrapper, $post, $entityManager);

        $entityManager->flush();

        $name = $sensorWrapper->getName();

        return $this->json([
            'success' => true,
            'msg' => "Le capteur <strong>${name}</strong> a bien été modifié."
        ]);
    }

    /**
     * @Route("/{id}/elements-associes", name="sensors_pairing_index", options={"expose"=true})
     * @HasPermission({Menu::IOT, Action::DISPLAY_SENSOR})
     */
    public function sensorPairingIndex($id, EntityManagerInterface $entityManager): Response
    {
        $sensor = $entityManager->getRepository(SensorWrapper::class)->find($id);
        return $this->render('IOT/sensors_pairing/index.html.twig', [
            'sensor' => $sensor
        ]);
    }

    /**
     * @Route("/elements-associes/api", name="sensors_pairing_api", options={"expose"=true}, methods={"POST"}, condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::IOT, Action::DISPLAY_SENSOR})
     */
    public function sensorPairingApi(Request $request,
                                     PairingService $pairingService,
                                     EntityManagerInterface $entityManager): Response {
        $sensorId = $request->query->get('sensor');
        $sensorRepository = $entityManager->getRepository(SensorWrapper::class);
        $sensorWrapper = $sensorRepository->find($sensorId);
        $data = $pairingService->getDataForDatatable($sensorWrapper, $request->request);
        return $this->json($data);
    }

}

