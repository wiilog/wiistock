<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Entity\CategoryType;
use App\Entity\FreeField;
use App\Entity\Language;
use App\Entity\Setting;
use App\Entity\Statut;
use App\Entity\Translation;
use App\Entity\TranslationSource;
use App\Entity\Type;
use App\Entity\Menu;
use App\Entity\Action;
use App\Exceptions\FormException;
use App\Service\TranslationService;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

/**
 * @Route("/parametrage/type")
 */
class TypeController extends AbstractController {

    /**
     * @Route("/type-api/{type}/edit/translate", name="settings_edit_type_translations_api", options={"expose"=true}, methods="GET", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT})
     */
    public function apiEditTranslations(EntityManagerInterface $manager,
                                        TranslationService $translationService,
                                        ?Type $type = null): JsonResponse
    {
        if(!$type) {
            $categoryTypeRepository = $manager->getRepository(CategoryType::class);
            $typeRepository = $manager->getRepository(Type::class);
            $category = $categoryTypeRepository->findOneBy(["label" => CategoryType::MOUVEMENT_TRACA]);
            $type = $typeRepository->findOneBy([
                "label" => CategoryType::MOUVEMENT_TRACA,
                "category" => $category,
            ]);
            $hideType = true;
        }

        if ($type->getLabelTranslation() === null) {
            $translationService->setFirstTranslation($manager, $type, $type->getLabel());
        }

        foreach ($type->getChampsLibres() as $freeField) {
            if ($freeField->getLabelTranslation() === null) {
                $translationService->setFirstTranslation($manager, $freeField, $freeField->getLabel());
            }

            if ($freeField->getDefaultValue() && $freeField->getDefaultValueTranslation() === null) {
                $translationService->setFirstTranslation($manager, $freeField, $freeField->getDefaultValue(), "setDefaultValueTranslation");
            }

            if($freeField->getElements() && $freeField->getElementsTranslations()->isEmpty()) {
                foreach($freeField->getElements() as $element) {
                    $translationService->setFirstTranslation($manager, $freeField, $element, "addElementTranslation");
                }
            }
        }

        $manager->flush();

        return $this->json([
            "success" => true,
            "html" => $this->renderView('settings/modal_edit_type_translations_content.html.twig', [
                "type" => $type,
                "hideType" => $hideType ?? false,
            ]),
        ]);
    }

    /**
     * @Route("/type/edit/translate", name="settings_edit_type_translations", options={"expose"=true}, methods="GET|POST", condition="request.isXmlHttpRequest()")
     * @HasPermission({Menu::PARAM, Action::EDIT}, mode=HasPermission::IN_JSON)
     */
    public function editTranslations(Request                $request,
                                     EntityManagerInterface $manager,
                                     TranslationService     $translationService): JsonResponse {
        $data = Stream::from($request->request->all())
            ->map(fn(string $json) => json_decode($json, true))
            ->toArray();

        $type = $manager->find(Type::class, $data["type"]);
        if(isset($data["label"])) {
            $translationService->editEntityTranslations($manager, $type->getLabelTranslation(), $data["label"]);
        }

        unset($data["type"], $data["label"]);
        foreach($data as $key => $translations) {
            [$field, $freeFieldId] = explode("-", $key);
            $french = $manager->getRepository(Language::class)->findOneBy(["slug" => Language::FRENCH_SLUG]);
            $freeField = $manager->find(FreeField::class, $freeFieldId);

            if($field === "label") {
                $translationService->editEntityTranslations($manager, $freeField->getLabelTranslation(), $translations);
                $freeField->setLabel($freeField->getLabelIn(Language::FRENCH_SLUG));
            } else if($field === "defaultValue") {
                if(!$freeField->getDefaultValueTranslation()) {
                    $source = new TranslationSource();
                    $manager->persist($source);

                    $freeField->setDefaultValueTranslation($source);
                }

                $translationService->editEntityTranslations($manager, $freeField->getDefaultValueTranslation(), $translations, "defaultValue");
                $freeField->setDefaultValue($freeField->getDefaultValueIn(Language::FRENCH_SLUG));
            } else if($field === "elements") {
                $formattedTranslations = Stream::from($translations)
                    ->filter(fn(array $item) => $item["elements"] ?? null)
                    ->keymap(fn(array $item) => [$item["language-id"], explode(";", $item["elements"])]);

                $freeField->setElements($formattedTranslations[$french->getId()]);

                if($formattedTranslations->some(fn(array $trans) => count($trans) !== count($freeField->getElements()))) {
                    throw new FormException("Les traductions des éléments du champ libre \"{$freeField->getLabel()}\" ne sont pas correctement renseignées");
                }

                foreach($freeField->getElementsTranslations() as $source) {
                    $freeField->removeElementTranslation($source);
                    $manager->remove($source);
                }

                $freeField->setElementTranslations([]);

                foreach($freeField->getElements() as $element) {
                    $translationService->setFirstTranslation($manager, $freeField, $element, "addElementTranslation");
                }

                foreach($formattedTranslations as $language => $elementsTranslations) {
                    //skip french it has already been added above
                    if($language == $french->getId()) {
                        continue;
                    }

                    $language = $manager->find(Language::class, $language);

                    for($i = 0; $i < count($elementsTranslations); $i++) {
                        /** @var TranslationSource $source */
                        $source = $freeField->getElementsTranslations()[$i] ?? null;

                        $translation = new Translation();
                        $translation->setLanguage($language);
                        $translation->setTranslation($elementsTranslations[$i]);
                        $source->addTranslation($translation);

                        $manager->persist($translation);
                    }
                }
            }
        }

        $manager->flush();

        return $this->json([
            "success" => true,
            "msg" => "Les traductions ont bien été enregistrées pour le type {$type->getLabel()}",
        ]);
    }

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

    /**
     * @Route("/champs-libres/{type}", name="free_fields_by_type", options={"expose"=true}, methods="POST", condition="request.isXmlHttpRequest()")
     */
    public function freeFieldsByType(Type $type, EntityManagerInterface $entityManager): Response
    {
        $freeFieldRepository = $entityManager->getRepository(FreeField::class);
        $settingRepository = $entityManager->getRepository(Setting::class);
        $allFreeFields = $freeFieldRepository->findByType($type->getId());
        $selectedFreeFieldId = $settingRepository->getOneParamByLabel(Setting::FREE_FIELD_REFERENCE_CREATE);
        $selectedFreeField = $selectedFreeFieldId ? $freeFieldRepository->find($selectedFreeFieldId) : null;

        $freeFields = [($selectedFreeField && $selectedFreeField->getType()->getId() === $type->getId()) ? "<option value=''></option><option selected value='{$selectedFreeField->getId()}'>{$selectedFreeField->getLabel()}</option>" : "<option selected value=''></option>"];

        $freeFields = array_merge($freeFields, Stream::from($allFreeFields)
            ->map(function (FreeField $freeField) use ($selectedFreeField) {
                if($freeField->getId() !== $selectedFreeField?->getId()){
                    return "<option value='{$freeField->getId()}'>{$freeField->getLabel()}</option>";
                }
            })
            ->toArray()) ;
        return new JsonResponse([
            'success' => true,
            'freeFields' => $freeFields
        ]);
    }


}

