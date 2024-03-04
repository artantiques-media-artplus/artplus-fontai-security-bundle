<?php
namespace Fontai\Bundle\SecurityBundle\Http\Firewall;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\SecurityEvents;


class InitHashAuthenticationListener
{
  protected $tokenStorage;
  protected $authenticationManager;
  protected $httpUtils;
  protected $providerKey;
  protected $successHandler;
  protected $failureHandler;
  protected $options;
  protected $logger;
  protected $eventDispatcher;

  public function __construct(
    TokenStorageInterface $tokenStorage,
    AuthenticationManagerInterface $authenticationManager,
    HttpUtils $httpUtils,
    $providerKey,
    AuthenticationSuccessHandlerInterface $successHandler = NULL,
    AuthenticationFailureHandlerInterface $failureHandler = NULL,
    array $options = [],
    LoggerInterface $logger = NULL,
    EventDispatcherInterface $eventDispatcher = NULL
  )
  {
    $this->tokenStorage = $tokenStorage;
    $this->authenticationManager = $authenticationManager;
    $this->httpUtils = $httpUtils;
    $this->providerKey = $providerKey;
    $this->successHandler  = $successHandler;
    $this->failureHandler = $failureHandler;
    $this->logger = $logger;
    $this->eventDispatcher = $eventDispatcher;
    $this->options = $options;
  }

  public function __invoke(RequestEvent $event)
  {
    $request = $event->getRequest();

    if (!$this->requiresAuthentication($request))
    {
      return;
    }

    try
    {
      $username = $this->options['query']::create()
      ->select('Email')
      ->filterByInit(FALSE)
      ->filterByInitHash($request->attributes->get('hash'))
      ->findOne();

      if (!$username)
      {
        throw new BadCredentialsException('Invalid init hash.');
      }

      $token = new PreAuthenticatedToken($username, NULL, $this->providerKey);

      $authenticatedToken = $this->authenticationManager->authenticate($token);
      $response = $this->onSuccess($request, $authenticatedToken);
    }
    catch (AuthenticationException $e)
    {
      $response = $this->onFailure($request, $e);
    }

    if (NULL === $response)
    {
      return;
    }

    $event->setResponse($response);
  }

  protected function requiresAuthentication(Request $request)
  {
    return $this->httpUtils->checkRequestPath($request, $this->options['check_path']);
  }

  protected function onSuccess(Request $request, TokenInterface $token)
  {
    if (NULL !== $this->logger)
    {
      $this->logger->info('User has been authenticated successfully.', ['username' => $token->getUsername()]);
    }

    $this->tokenStorage->setToken($token);

    if (NULL !== $this->eventDispatcher)
    {
      $loginEvent = new InteractiveLoginEvent($request, $token);
      $this->eventDispatcher->dispatch($loginEvent, SecurityEvents::INTERACTIVE_LOGIN);
    }

    $response = $this->successHandler->onAuthenticationSuccess($request, $token);

    if (!$response instanceof Response)
    {
      throw new \RuntimeException('Authentication Success Handler did not return a Response.');
    }

    return $response;
  }

  protected function onFailure(Request $request, AuthenticationException $failed)
  {
    if (NULL !== $this->logger)
    {
      $this->logger->info('Authentication request failed.', ['exception' => $failed]);
    }

    $token = $this->tokenStorage->getToken();

    if ($token instanceof PreAuthenticatedToken && $this->providerKey === $token->getProviderKey())
    {
      $this->tokenStorage->setToken(NULL);
    }

    $response = $this->failureHandler->onAuthenticationFailure($request, $failed);

    if (!$response instanceof Response)
    {
      throw new \RuntimeException('Authentication Failure Handler did not return a Response.');
    }

    return $response;
  }
}
