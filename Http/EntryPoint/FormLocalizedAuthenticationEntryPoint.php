<?php
namespace Fontai\Bundle\SecurityBundle\Http\EntryPoint;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\HttpUtils;


class FormLocalizedAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
  private $loginPath;
  private $useForward;
  private $httpKernel;
  private $httpUtils;

  public function __construct(
    HttpKernelInterface $kernel,
    HttpUtils $httpUtils,
    $loginPath,
    $useForward = false
  )
  {
    $this->httpKernel = $kernel;
    $this->httpUtils = $httpUtils;
    $this->loginPath = $loginPath;
    $this->useForward = (bool) $useForward;
  }

  public function start(
    Request $request,
    AuthenticationException $authException = null
  )
  {
    $loginPath = sprintf('%s.%s', $this->loginPath, $request->getLocale());

    if ($this->useForward)
    {
      $subRequest = $this->httpUtils->createRequest($request, $loginPath);
      $response = $this->httpKernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);

      if (200 === $response->getStatusCode())
      {
        $response->setStatusCode(401);
      }

      return $response;
    }

    return $this->httpUtils->createRedirectResponse($request, $loginPath);
  }
}
