<?php

namespace Creads\SocialBanner\App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Validator\Constraints as Assert;

class CommentFormType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('message', TextAreaType::class, array(
          'constraints' => array(new Assert\NotBlank()),
          'required' => true,
          'attr' => array('class' => 'form-control', 'rows' => 2),
        ))
        ->add('projectUri', HiddenType::class, [
          'constraints' => array(new Assert\NotBlank()),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'comment';
    }
}
