<?php
namespace Fontai\Bundle\SecurityBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Context\ExecutionContextInterface;


class UserRecoveryType extends AbstractType
{
  public function buildForm(FormBuilderInterface $builder, array $options)
  {
    $builder->add('email', Type\EmailType::class, [
      'label'         => 'E-mail',
      'required'      => FALSE,
      'constraints'   => [
        new Constraints\NotBlank()
      ]
    ]);
  }

  public function configureOptions(OptionsResolver $resolver)
  {
    $resolver->setDefaults([
      'translation_domain' => function (Options $options)
      {
        return $options['section'];
      }
    ]);

    $resolver->setRequired([
      'section',
      'entity_user'
    ]);
  }
}
