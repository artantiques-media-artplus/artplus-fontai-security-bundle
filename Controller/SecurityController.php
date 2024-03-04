<?php
namespace Fontai\Bundle\SecurityBundle\Controller;

use Fontai\Bundle\SecurityBundle\Form\UserRecoveryType;
use Fontai\Bundle\SecurityBundle\Form\UserRecoveryFinalizeType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\HttpUtils;


class SecurityController extends AbstractController
{
  protected $sectionName;
  protected $userEntityClass;
  protected $isRoutingLocalized;

  public function __construct(
    string $sectionName,
    string $userEntityClass,
    bool $isRoutingLocalized
  )
  {
    $this->sectionName = $sectionName;
    $this->userEntityClass = $userEntityClass;
    $this->isRoutingLocalized = $isRoutingLocalized;
  }

  public function login(
    Request $request,
    AuthenticationUtils $authenticationUtils,
    string $template
  )
  {
    $email = $authenticationUtils->getLastUsername();
    $request->getSession()->remove(Security::LAST_USERNAME);

    return $this->render($template, [
      'email' => $email,
      'error' => $authenticationUtils->getLastAuthenticationError()
    ]);
  }

  public function logout(
    Request $request,
            HttpUtils $httpUtils,
    HttpKernelInterface $httpKernel,
    string $logoutPath
  )
  {
    $subRequest = $httpUtils->createRequest($request, $logoutPath);
    $response = $httpKernel->handle($subRequest);

    return $response;
  }

  public function recovery(
    Request $request,
    AuthenticationManagerInterface $authenticationManager,
    TokenStorageInterface $tokenStorage,
    UserPasswordHasherInterface $passwordHasher,
    string $template,
    string $target,
    string $hash = NULL
  )
  {
    if ($hash && ($user = $this->getUserByRecoveryHash($hash)))
    {
      $form = $this->createForm(
        UserRecoveryFinalizeType::class,
        $user,
        [
          'section' => $this->sectionName,
          'entity_user' => $this->userEntityClass
        ]
      )
      ->handleRequest($request);

      if ($form->isSubmitted() && $form->isValid())
      {
        $user
        ->setPassword($passwordHasher->hashPassword($user, $form['password']->getData()))
        ->setRecoveryHash(NULL)
        ->setRecoveryHashCreatedAt(NULL)
        ->save();

        $token = new UsernamePasswordToken(
          $user->getUsername(),
          $form['password']->getData(),
          $this->sectionName
        );

        $authenticationManager->authenticate($token);
        $tokenStorage->setToken($token);

        return $this->redirectToRoute($target . ($this->isRoutingLocalized ? '.' . $request->getLocale() : NULL));
      }
    }
    else
    {
      $form = $this->createForm(
        UserRecoveryType::class,
        ['email' => $request->query->get('email')],
        [
          'section' => $this->sectionName,
          'entity_user' => $this->userEntityClass
        ]
      )
      ->handleRequest($request);

      if ($form->isSubmitted() && $form->isValid())
      {
        $user = $this->getUserByEmail($form['email']->getData());

        if ($user)
        {
          $user
          ->generateRecoveryHash()
          ->save();
        }

        $this->addFlash('notice', 'Instrukce pro změnu hesla byly úspěšně odeslány.');

        return $this->redirect($request->getUri());
      }
    }

    return $this->render($template, [
      'form' => $form->createView()
    ]);
  }

  protected function getQuery()
  {
    return call_user_func([sprintf('%sQuery', $this->userEntityClass), 'create']);
  }

  protected function getUserByEmail(string $email)
  {
    return $this->getQuery()
    ->filterByInit(TRUE)
    ->filterByEmail($email)
    ->findOne();
  }

  protected function getUserByRecoveryHash(string $recoveryHash)
  {
    return $this->getQuery()
    ->filterByInit(TRUE)
    ->filterByRecoveryHashCreatedAt(['min' => '-1 hour'])
    ->filterByRecoveryHash($recoveryHash)
    ->findOne();
  }
}