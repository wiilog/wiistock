<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TestCLBController extends AbstractController
{

    /**
     * @Route("/informations-patient", name="get_patient_infos", methods="GET", options={"expose"=true})
     */
    public function getInformations(Request $request): Response
    {

        $token = $request->query->get('x-api-key');

        $tokenIsValid = $token === $_SERVER['CLB_API_KEY'];
        $content = $request->query->get('content');
        if (!$content) {
            $response = false;
        } else {
            $content = str_replace(["\r"], '\\r', $content);
            $content = str_replace(["\n"], '\\n', $content);
            $response = json_decode($content, true);
        }

        return $this->render('test_clb.html.twig', [
            'informations' => $response,
            'validToken' => $tokenIsValid,
            'rawContent' => $content
        ]);
    }


    /**
     * @Route("/informations-patient/v2", name="get_patient_infos_v2", methods="GET", options={"expose"=true})
     */
    public function getInformationsV2(Request $request): Response
    {

        $now = new \DateTime();

        $encryption_key = "66cc97446d49e21ef96837ea9dcfcc26";
        $encryption_iv = "2beff89f332630c6";

        $token = $request->query->get('x-api-key');
        $tokenDecrypted = openssl_decrypt(
            $token,
            'AES-256-CBC',
            $encryption_key,
            0,
            $encryption_iv
        );

        $tokenIsValid = $tokenDecrypted === $now->format('Ymd');
        $parameters = $request->query->all();

        $content = $parameters;

        if (empty($content)) {
            $response = false;
        } else {
            $response = $content;
        }

        return $this->render('test_clb.html.twig', [
            'informations' => $response,
            'validToken' => $tokenIsValid
        ]);
    }
}
