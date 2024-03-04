<?php
namespace Fontai\Bundle\SecurityBundle\Model;


trait UserTrait
{
  public function serialize()
  {
    return serialize([
      $this->id,
      $this->email,
      $this->password
    ]);
  }

  public function unserialize($serialized)
  {
    list (
      $this->id,
      $this->email,
      $this->password
    ) = unserialize($serialized);
  }

  public function getRoles()
  {
    return ['ROLE_USER'];
  }

  public function getSalt()
  {
    return NULL;
  }

  public function getUsername()
  {
    return $this->getEmail();
  }
  
  public function eraseCredentials()
  {
  }

  public function generateInitHash()
  {
    while (1)
    {
      $initHash = bin2hex(random_bytes(20));
      $count = call_user_func([sprintf('%sQuery', static::class), 'create'])
      ->filterByInitHash($initHash)
      ->count();
      
      if (!$count)
      {
        break;
      }
    }

    return $this
    ->setInit(FALSE)
    ->setInitHash($initHash);
  }

  public function generateRecoveryHash()
  {
    while (1)
    {
      $recoveryHash = bin2hex(random_bytes(20));
      $count = call_user_func([sprintf('%sQuery', static::class), 'create'])
      ->filterByRecoveryHash($recoveryHash)
      ->count();
      
      if (!$count)
      {
        break;
      }
    }

    return $this
    ->setRecoveryHash($recoveryHash)
    ->setRecoveryHashCreatedAt('now');
  }
}
