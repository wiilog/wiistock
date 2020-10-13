<?php

namespace App\Form;

use App\Entity\Emplacement;
use App\Entity\TransferRequest;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TransferRequestType extends AbstractType {

    public function buildForm(FormBuilderInterface $builder, array $options) {
        $builder
            ->add("requester", TextType::class, [
                "data" => $builder->getData()->getRequester()->getUsername(),
                "mapped" => false,
                "disabled" => true,
            ])
            ->add("destination", EntityType::class, [
                "class" => Emplacement::class,
                "choice_name" => "label",
                "placeholder" => "Sélectionnez un emplacement...",
            ])
            ->add("comment", TextareaType::class, [
                "required" => false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver) {
        $resolver->setDefaults([
            "data_class" => TransferRequest::class,
        ]);
    }

}
