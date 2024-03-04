<?php
namespace Fontai\Bundle\SecurityBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;


class UserRecoveryFinalizeType extends AbstractType
{
  public function buildForm(FormBuilderInterface $builder, array $options)
  {
    $builder->add('password', Type\RepeatedType::class, [
      'required'        => FALSE,
      'mapped'          => FALSE,
      'type'            => Type\PasswordType::class,
      'invalid_message' => 'Hesla se neshodují.',
      'first_options'   => [
        'label' => 'Nové heslo'
      ],
      'second_options'  => [
        'label' => 'Potvrzení nového hesla'
      ],
      'constraints'     => [
        new Constraints\NotBlank([
          'message' => 'Prázdné heslo.'
        ]),
        new Constraints\Length([
          'min'        => 6
        ])
      ]
    ]);
  }

  public function configureOptions(OptionsResolver $resolver)
  {
    $resolver->setDefaults([
      'data_class' => function (Options $options)
      {
        return $options['entity_user'];
      },
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
