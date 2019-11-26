<?php

namespace App\Controller;

use App\Entity\Menu;
use App\Service\UserService;
use http\Client\Curl\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

use App\Entity\MailerServer;
use App\Repository\MailerServerRepository;

class MailerServerController extends AbstractController
{

    /**
     * @var MailerServerRepository
     */
    private $mailerServerRepository;

    /**
     * @var UserService
     */
    private $userService;


    public function __construct(MailerServerRepository $mailerServerRepository, UserService $userService)
    {
        $this->mailerServerRepository = $mailerServerRepository;
        $this->userService = $userService;
    }

    /**
     * @Route("/mailer/server", name="mailer_server_index")
     */
    public function index(): response
    {
        if (!$this->userService->hasRightFunction(Menu::PARAM)) {
            return $this->redirectToRoute('access_denied');
        }

        $mailerServer =  $this->mailerServerRepository->findOneMailerServer();
        return $this->render('mailer_server/index.html.twig', [
            'mailerServer' => $mailerServer
        ]);
    }


    /**
     * @Route("/ajax-mail-server", name="ajax_mailer_server",  options={"expose"=true},  methods="GET|POST")
     */
    public function ajaxMailerServer(Request $request): response
    {
        if (!$request->isXmlHttpRequest() && $data = json_decode($request->getContent(), true)) {
            if (!$this->userService->hasRightFunction(Menu::PARAM)) {
                return $this->redirectToRoute('access_denied');
            }

            $em = $this->getDoctrine()->getEntityManager();
            $mailerServer =  $this->mailerServerRepository->findOneMailerServer();
            if ($mailerServer === null) {
                $mailerServerNew = new MailerServer;
                $mailerServerNew
                    ->setUser($data['user'])
                    ->setPassword($data['password'])
                    ->setPort($data['port'])
                    ->setProtocol($data['protocol'])
                    ->setSmtp($data['smtp']);
                $em->persist($mailerServerNew);
            } else {
                $mailerServer
                    ->setUser($data['user'])
                    ->setPassword($data['password'])
                    ->setPort($data['port'])
                    ->setProtocol($data['protocol'])
                    ->setSmtp($data['smtp']);
            }
            $em->flush();

            return new JsonResponse($data);
        }
        throw new NotFoundHttpException("404");
    }
}
