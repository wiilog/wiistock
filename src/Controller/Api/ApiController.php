<?php

namespace App\Controller\Api;

use App\Controller\AbstractController;
use App\Entity\Setting;
use App\Exceptions\FormException;
use App\Service\MobileApiService;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[Route("/api", name: "api_")]
class ApiController extends AbstractController {

    #[Route("/ping", name: 'ping', options: ["expose" => true], methods: [self::GET])]
    public function ping(): JsonResponse {
        return $this->json([
            'success' => true,
        ]);
    }

//    TODO WIIS-11587 delete
    #[Route("/nomade-versions", methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    public function checkNomadeVersion(Request $request,
                                       MobileApiService $mobileApiService,
                                       ParameterBagInterface $parameterBag): JsonResponse {
        return $this->json([
            "success" => true,
            "validVersion" => $mobileApiService->checkMobileVersion($request->get('nomadeVersion'), $parameterBag->get('nomade_version')),
        ]);
    }

//    TODO WIIS-11587 delete
    #[Route("/server-images", methods: [self::GET], condition: self::IS_XML_HTTP_REQUEST)]
    public function getLogos(EntityManagerInterface $entityManager,
                             SettingsService        $settingsService,
                             KernelInterface        $kernel,
                             Request                $request): Response
    {
        $logoKey = $request->get('key');
        if (!in_array($logoKey, [Setting::FILE_MOBILE_LOGO_HEADER, Setting::FILE_MOBILE_LOGO_LOGIN])) {
            throw new BadRequestHttpException('Unknown logo key');
        }

        $logo = $settingsService->getValue($entityManager, $logoKey);

        if (!$logo) {
            throw new FormException("Image non renseignée");
        }

        $projectDir = $kernel->getProjectDir();

        try {
            $imagePath = $projectDir . '/public/' . $logo;

            $type = pathinfo($imagePath, PATHINFO_EXTENSION);
            $type = ($type === 'svg' ? 'svg+xml' : $type);

            $data = file_get_contents($imagePath);
            $image = 'data:image/' . $type . ';base64,' . base64_encode($data);
        } catch (Throwable) {
            throw new FormException("Image non renseignée");
        }

        return $this->json([
            "success" => true,
            'image' => $image,
        ]);
    }
}
