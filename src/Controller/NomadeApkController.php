<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class NomadeApkController extends AbstractController {

    /**
     * @Route("/telecharger/nomade.apk", name="download_nomade_apk", methods={"GET"})
     */
    public function index(): Response {
        $apkUrl = $this->getParameter('nomade_apk');
        return $this->redirect($apkUrl);
    }
}
