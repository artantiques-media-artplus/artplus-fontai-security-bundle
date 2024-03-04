<?php
namespace Fontai\Bundle\SecurityBundle\Http\Firewall;

use App\Model;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Firewall\UsernamePasswordFormAuthenticationListener;


class UsernamePasswordFormLocalizedAuthenticationListener extends UsernamePasswordFormAuthenticationListener
{
  protected function requiresAuthentication(Request $request)
  {
    if ($this->options['post_only'] && !$request->isMethod('POST'))
    {
      return FALSE;
    }

    foreach (explode(',', $this->options['route_locale']) as $locale)
    {
      if ($this->httpUtils->checkRequestPath($request, sprintf('%s.%s', $this->options['check_path'], $locale)))
      {
        return TRUE;
      }
    }

    return FALSE;
  }
}
