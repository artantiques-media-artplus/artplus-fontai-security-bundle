<?php
namespace Fontai\Bundle\SecurityBundle\EventListener;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Component\Security\Core\AuthenticationEvents;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Event\AuthenticationEvent;
use Symfony\Component\Security\Core\Event\AuthenticationFailureEvent;


class SecurityListener
{
  protected $requestStack;
  protected $router;
  protected $tokenStorage;
  protected $sectionName;
  protected $userEntityClass;
  protected $loginAttemptEntityClass;
  protected $initTarget;
  protected $isRoutingLocalized;

  public function __construct(
    RequestStack $requestStack,
    RouterInterface $router,
    TokenStorageInterface $tokenStorage,
    string $sectionName,
    string $userEntityClass,
    string $loginAttemptEntityClass,
    string $initTarget,
    bool $isRoutingLocalized
  )
  {
    $this->requestStack = $requestStack;
    $this->router = $router;
    $this->tokenStorage = $tokenStorage;
    $this->sectionName = $sectionName;
    $this->userEntityClass = $userEntityClass;
    $this->loginAttemptEntityClass = $loginAttemptEntityClass;
    $this->initTarget = $initTarget;
    $this->isRoutingLocalized = $isRoutingLocalized;
  }

  public function onKernelRequest(RequestEvent $event)
  {
    $token = $this->tokenStorage->getToken();

    if (!$token || !$this->isCorrectToken($token))
    {
      return;
    }
    
    $user = $this->tokenStorage->getToken()->getUser();

    if (!is_object($user))
    {
      return;
    }

    $request = $event->getRequest();

    $route = $this->initTarget . ($this->isRoutingLocalized ? '.' . $request->getLocale() : NULL);

    if (!$user->getInit() && $request->attributes->get('_route') != $route)
    {
      $response = new RedirectResponse($this->router->generate($route));
      $event->setResponse($response);
    }
  }

  public function onInteractiveLogin(InteractiveLoginEvent $event)
  {
    $token = $event->getAuthenticationToken();

    if (!$this->isCorrectToken($token))
    {
      return;
    }

    $user = $token->getUser();

    $user
    ->setLastLoginAt('now')
    ->setLoginCount($user->getLoginCount() + 1)
    ->save();
  }

  public function onAuthenticationSuccess(AuthenticationEvent $event)
  {
    $token = $event->getAuthenticationToken();

    if (!$this->isCorrectToken($token))
    {
      return;
    }

    $user = $token->getUser();

    if (!is_object($user))
    {
      return;
    }

    $user
    ->setLastActivityAt('now')
    ->save();
  }

  public function onAuthenticationFailure(AuthenticationFailureEvent $event)
  {
    $token = $event->getAuthenticationToken();

    if (!$this->isCorrectToken($token))
    {
      return;
    }

    $user = call_user_func([sprintf('%sQuery', $this->userEntityClass), 'create'])
    ->filterByInit(TRUE)
    ->filterByEmail($token->getUser())
    ->findOne();

    if ($user)
    {
      $userEntityClassParts = explode('\\', $this->userEntityClass);
      
      $loginAttempt = new $this->loginAttemptEntityClass();
      $loginAttempt
      ->setCreatedAt('now')
      ->{sprintf('set%s', end($userEntityClassParts))}($user)
      ->setIp($this->requestStack->getCurrentRequest()->getClientIp())
      ->save();
    }
  }

  protected function isCorrectToken(TokenInterface $token)
  {
    return method_exists($token, 'getProviderKey') && $token->getProviderKey() == $this->sectionName;
  }
}