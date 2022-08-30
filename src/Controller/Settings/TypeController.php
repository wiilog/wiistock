<?php

namespace App\Controller\Settings;

use App\Entity\Statut;
use App\Entity\Type;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/parametrage/type")
 */
class TypeController extends AbstractController {

    /**
     * @Route("/verification/{type}", name="settings_types_check_delete", methods={"GET"}, options={"expose"=true}, condition="request.isXmlHttpRequest()")
     */
    public function checkTypeCanBeDeleted(Type $type,
                                          EntityManagerInterface $entityManager): Response {
        $typeRepository = $entityManager->getRepository(Type::class);
        $statusRepository = $entityManager->getRepository(Statut::class);

        $canDelete = !$typeRepository->isTypeUsed($type->getId());
        $usedStatuses = $statusRepository->count(['type' => $type]);

        $success = $canDelete && $usedStatuses === 0;

        if ($success) {
            $message = 'Voulez-vous réellement supprimer ce type ?';
        }
        else if (!$canDelete) {
            $hasNoFreeFields = $type->getChampsLibres()->isEmpty();
            $message = $hasNoFreeFields
                ? 'Ce type est utilisé, vous ne pouvez pas le supprimer.'
                : 'Des champs libres sont liés à ce type, veuillez les supprimer avant de procéder à la suppression du type';
        }
        else {
            $message = 'Ce type est lié à des statuts, veuillez les supprimer avant de procéder à la suppression du type';
        }

        return new JsonResponse([
            'success' => $success,
            'message' => $message
        ]);
    }

    /**
     * @Route("/supprimer/{type}", name="settings_types_delete", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     */
    public function delete(Type $type,
                           EntityManagerInterface $entityManager): Response
    {
        $typeLabel = $type->getLabel();

        $entityManager->remove($type);
        $entityManager->flush();
        return new JsonResponse([
            'success' => true,
            'message' => 'Le type <strong>' . $typeLabel . '</strong> a bien été supprimé.'
        ]);
    }
}

