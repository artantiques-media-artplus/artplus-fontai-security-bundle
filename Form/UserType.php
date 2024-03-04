<?php
namespace Fontai\Bundle\SecurityBundle\Form;

use Fontai\Bundle\GeneratorBundle\Validator\Constraints as FontaiConstraints;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints as SecurityConstraints;
use Symfony\Component\Validator\Constraints;


class UserType extends AbstractType
{
  public function buildForm(FormBuilderInterface $builder, array $options)
  {
    $data = $builder->getData();

    $builder->add('email', Type\EmailType::class, [
      'label'         => 'E-mail',
      'required'      => FALSE,
      'constraints'   => [
        new Constraints\NotBlank(),
        new Constraints\Email(),
        new FontaiConstraints\Unique([
          'fields'  => ['email'],
          'message' => 'Duplicitní e-mailová adresa.',
          'object'  => $data
        ])
      ]
    ]);

    $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event)
    {
      $user = $event->getData();
      $form = $event->getForm();

      $passwordConstraints = [
        new Constraints\Length([
          'groups'     => 'password',
          'min'        => 6
        ])
      ];

      if (!$user->getInit())
      {
        $passwordConstraints[] = new Constraints\NotBlank();
      }
      else
      {
        $form->add('password_old', Type\PasswordType::class, [
          'label'         => 'Současné heslo',
          'required'      => FALSE,
          'mapped'        => FALSE,
          'constraints'   => [
            new SecurityConstraints\UserPassword([
              'groups'  => 'password',
              'message' => 'Neplatné současné heslo.'
            ])
          ]
        ]);
      }

      $form->add('password', Type\RepeatedType::class, [
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
        'constraints'     => $passwordConstraints
      ]);
    });
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
      },
      'validation_groups'  => function (FormInterface $form)
      {
        $groups = ['Default'];

        if ($form->get('password')->getData())
        {
          $groups[] = 'password';
        }

        return $groups;
      }
    ]);

    $resolver->setRequired([
      'section',
      'entity_user'
    ]);
  }
}
