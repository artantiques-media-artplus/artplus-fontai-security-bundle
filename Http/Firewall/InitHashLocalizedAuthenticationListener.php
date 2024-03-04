<?php
namespace Fontai\Bundle\SecurityBundle\Http\Firewall;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;


class InitHashLocalizedAuthenticationListener extends InitHashAuthenticationListener
{
  protected function requiresAuthentication(Request $request)
  {
    $isMatch = FALSE;

    foreach (explode(',', $this->options['route_locale']) as $locale)
    {
      if ($this->httpUtils->checkRequestPath($request, sprintf('%s.%s', $this->options['check_path'], $locale)))
      {
        $this->updateHandlersTargetPath($request);

        return TRUE;
      }
    }

    return FALSE;
  }

  protected function updateHandlersTargetPath(Request $request)
  {
    $options = $this->successHandler->getOptions();
    $options['default_target_path'] = sprintf('%s.%s', $options['default_target_path'], $request->getLocale());
    $this->successHandler->setOptions($options);

    $options = $this->failureHandler->getOptions();
    $options['failure_path'] = sprintf('%s.%s', $options['failure_path'], $request->getLocale());
    $this->failureHandler->setOptions($options);
  }
}
