<?php
namespace Fontai\Bundle\SecurityBundle\Security\User;

use Propel\Bundle\PropelBundle\Security\User\PropelUserProvider as BasePropelUserProvider;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;


class PropelUserProvider extends BasePropelUserProvider
{
  public function loadUserByUsername($username)
  {
    $queryClass = $this->queryClass;
    $query = $queryClass::create();

    if (NULL !== $this->property)
    {
      $this->property = explode(',', $this->property);

      foreach (array_values($this->property) as $i => $property)
      {
        if ($i)
        {
          $query->_or();
        }
        
        $property = ucfirst($property);
        $value = is_object($username) ? $username->{sprintf('get%s', $property)}() : $username;

        $query->{sprintf('filterBy%s', $property)}($value);
      }
    }
    else
    {
      $query->filterByUsername(is_object($username) ? $username->getUsername() : $username);
    }

    if (NULL === $user = $query->findOne())
    {
      throw new UsernameNotFoundException(sprintf('User "%s" not found.', $username));
    }

    return $user;
  }
}
