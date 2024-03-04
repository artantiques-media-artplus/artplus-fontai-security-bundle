<?php
namespace Fontai\Bundle\SecurityBundle\Security;

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\Exception\LockedException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;


class UserChecker implements UserCheckerInterface
{
  protected $requestStack;
  protected $userEntityClass;
  protected $loginAttemptEntityClass;

  public function __construct(
    RequestStack $requestStack,
    string $userEntityClass,
    string $loginAttemptEntityClass
  )
  {
    $this->requestStack = $requestStack;
    $this->userEntityClass = $userEntityClass;
    $this->loginAttemptEntityClass = $loginAttemptEntityClass;
  }

  public function checkPreAuth(UserInterface $user)
  {
    if (!$user instanceof $this->userEntityClass)
    {
      return;
    }

    if (method_exists($user, 'getIsActive') && !$user->getIsActive())
    {
      $exception = new DisabledException('User account is disabled.');
      $exception->setUser($user);
      throw $exception;
    }

    if ($this->loginAttemptsExceeded($user))
    {
      $exception = new LockedException('User account is locked.');
      $exception->setUser($user);
      throw $exception;
    }
  }

  public function checkPostAuth(UserInterface $user)
  {
  }

  protected function loginAttemptsExceeded(ActiveRecordInterface $user)
  {
    $userEntityClassParts = explode('\\', $this->userEntityClass);

    $count = call_user_func([sprintf('%sQuery', $this->loginAttemptEntityClass), 'create'])
    ->{sprintf('filterBy%s', end($userEntityClassParts))}($user)
    ->filterByCreatedAt(['min' => '-1 hour'])
    ->filterByIp($this->requestStack->getCurrentRequest()->getClientIp())
    ->count();

    return $count >= 5;
  }
}