<?php

namespace App\Controller;

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

    public function __construct(MailerServerRepository $mailerServerRepository)
    {
        $this->mailerServerRepository = $mailerServerRepository;
    }

    /**
     * @Route("/mailer/server", name="mailer_server")
     */
    public function index(): response
    {
        $mailerServer =  $this->mailerServerRepository->getOneMailerServer();
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
            $em = $this->getDoctrine()->getEntityManager();
            $mailerServer =  $this->mailerServerRepository->getOneMailerServer();
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
