<?php

namespace App\Controller\IOT;

use App\Annotation\HasPermission;
use App\Entity\Action;
use App\Entity\IOT\Sensor;
use App\Entity\IOT\SensorWrapper;
use App\Entity\Menu;
use App\Entity\Utilisateur;
use App\Helper\PostHelper;

use App\Service\IOT\PairingService;
use App\Service\IOT\SensorWrapperService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;


/**
 * @Route("/iot/capteur")
 */
class SensorWrapperController extends AbstractController
{
    /**
     * @Route("/liste", name="sensor_wrapper_index")
     * @HasPermission({Menu::IOT, Action::DISPLAY_SENSOR})
     */
    public function index(): Response
    {
        return $this->render('iot/sensor_wrapper/index.html.twig');
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
    public function delete(Request $request,
                           EntityManagerInterface $entityManager): Response {

        if($data = json_decode($request->getContent(), true)) {
            $sensorWrapper = $entityManager->getRepository(SensorWrapper::class)->find($data['id']);

            $name = $sensorWrapper->getName();

            $entityManager->remove($sensorWrapper);
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
                        EntityManagerInterface $entityManager): Response
    {

        $post = $request->request;

        $name = PostHelper::string($post, 'name');
        $manager = PostHelper::entity($post, 'manager', Utilisateur::class);

        $sensorWrapper = new SensorWrapper();
        $sensorWrapper
            ->setName($name)
            ->setManager($manager);

        $entityManager->persist($sensorWrapper);
        $entityManager->flush();

        $name = $sensorWrapper->getName();

        return $this->json([
            'success' => true,
            'msg' => "Le capteur <strong>${name}</strong> a bien été créé"
        ]);
    }

    /**
     * @Route("/api-modifier", name="sensor_wrapper_api_edit", options={"expose"=true},  methods="GET|POST", condition="request.isXmlHttpRequest")
     * @HasPermission({Menu::IOT, Action::EDIT})
     */
    public function editApi(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $sensorWrapper = $entityManager->getRepository(SensorWrapper::class)->find($data['id']);

            $json = $this->renderView('iot/sensor_wrapper/edit_content.html.twig', [
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
                         Request $request): Response {

        $post = $request->request;
        $sensorWrapper = $entityManager->getRepository(SensorWrapper::class)->find($post->get('id'));

        $name = PostHelper::string($post, 'name', $sensorWrapper->getName());
        $manager = PostHelper::entity($post, 'manager', Utilisateur::class, $sensorWrapper->getManager());

        $sensorWrapper
            ->setName($name)
            ->setManager($manager);

        $entityManager->flush();

        $name = $sensorWrapper->getName();

        return $this->json([
            'success' => true,
            'msg' => "Le capteur <strong>${name}</strong> a bien été modifié"
        ]);
    }

    /**
     * @Route("/{id}/elements-associes", name="sensors_pairing_index", options={"expose"=true})
     * @HasPermission({Menu::IOT, Action::DISPLAY_SENSOR})
     */
    public function sensorPairingIndex($id, EntityManagerInterface $entityManager): Response
    {
        $sensor = $entityManager->getRepository(Sensor::class)->find($id);
        return $this->render('iot/sensors_pairing/index.html.twig', [
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
        $sensor = $entityManager->getRepository(Sensor::class)->find($sensorId);
        $data = $pairingService->getDataForDatatable($sensor, $request->request);
        return $this->json($data);
    }

}

