<?php

namespace App\Controller\Settings;

use App\Entity\FreeField;
use App\Entity\Type;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/parametrage")
 */
class FreeFieldController extends AbstractController {

    /**
     * @Route("/display-require-champ", name="display_required_champs_libres", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     */
    public function displayRequiredChampsLibres(Request $request,
                                                EntityManagerInterface $entityManager): Response
    {
        if ($data = json_decode($request->getContent(), true)) {
            $champLibreRepository = $entityManager->getRepository(FreeField::class);
            $typeRepository = $entityManager->getRepository(Type::class);

            if (array_key_exists('create', $data)) {
                $type = $typeRepository->find($data['create']);
                $champsLibres = $champLibreRepository->getByTypeAndRequiredCreate($type);
            } else if (array_key_exists('edit', $data)) {
                $type = $typeRepository->find($data['edit']);
                $champsLibres = $champLibreRepository->getByTypeAndRequiredEdit($type);
            } else {
                $json = false;
                return new JsonResponse($json);
            }
            $json = [];
            foreach ($champsLibres as $champLibre) {
                $json[] = $champLibre['id'];
            }
            return new JsonResponse($json);
        }
        throw new BadRequestHttpException();
    }
}
