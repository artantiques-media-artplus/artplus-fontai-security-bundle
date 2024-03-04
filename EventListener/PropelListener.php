<?php
namespace Fontai\Bundle\SecurityBundle\EventListener;

use App\Model\EmailTemplateQuery;
use Fontai\Propel\Behavior\EventDispatcher\Event\PropelEvent;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;


class PropelListener
{
  protected $mailer;
  protected $router;
  protected $sectionName;
  protected $userEntityClass;
  protected $initTemplateName;
  protected $recoveryTemplateName;
  protected $isRoutingLocalized;

  public function __construct(
    \Swift_Mailer $mailer,
    RouterInterface $router,
    string $sectionName,
    string $userEntityClass,
    string $initTemplateName,
    string $recoveryTemplateName,
    bool $isRoutingLocalized
  )
  {
    $this->mailer = $mailer;
    $this->router = $router;
    $this->sectionName = $sectionName;
    $this->userEntityClass = $userEntityClass;
    $this->initTemplateName = $initTemplateName;
    $this->recoveryTemplateName = $recoveryTemplateName;
    $this->isRoutingLocalized = $isRoutingLocalized;
  }

  public function onUserPreSave(PropelEvent $event)
  {
    $user = $this->getUserFromEvent($event);

    if (!$user->getInitHash())
    {
      $user->generateInitHash();
    }

    if ($user->isColumnModified(constant(sprintf(
      '%s::COL_INIT_HASH',
      constant(sprintf('%s::TABLE_MAP', $this->userEntityClass))
    ))))
    {
      $user->setInitUrl($this->getInitUrl($user));
    }
  }

  public function onUserPostSave(PropelEvent $event)
  {
    $user = $this->getUserFromEvent($event);

    if (!$user->getInit() && $user->wasColumnModified(constant(sprintf(
      '%s::COL_INIT_HASH',
      constant(sprintf('%s::TABLE_MAP', $this->userEntityClass))
    ))))
    {
      $this->sendInitEmail($user);
    }

    if (
      $user->wasColumnModified(constant(sprintf(
        '%s::COL_RECOVERY_HASH',
        constant(sprintf('%s::TABLE_MAP', $this->userEntityClass))
      )))
      && $user->getRecoveryHash()
    )
    {
      $this->sendRecoveryEmail($user);
    }
  }

  protected function getUserFromEvent(PropelEvent $event)
  {
    $userEntityClassParts = explode('\\', $this->userEntityClass);

    return $event->{sprintf('get%s', end($userEntityClassParts))}();
  }

  protected function sendInitEmail(ActiveRecordInterface $user)
  {
    $emailTemplate = EmailTemplateQuery::create()
    ->joinWithI18n(method_exists($user, 'getCulture') ? $user->getCulture() : 'cs')
    ->findOneByTid($this->initTemplateName);

    if ($emailTemplate)
    {
      $message = $emailTemplate->createEmail([
        '::URL::' => $user->getInitUrl()
      ])
      ->setTo($user->getEmail());
      
      $this
      ->mailer
      ->send($message);
    }
  }

  protected function sendRecoveryEmail(ActiveRecordInterface $user)
  {
    $emailTemplate = EmailTemplateQuery::create()
    ->joinWithI18n(method_exists($user, 'getCulture') ? $user->getCulture() : 'cs')
    ->findOneByTid($this->recoveryTemplateName);

    if ($emailTemplate)
    {
      $message = $emailTemplate->createEmail([
        '::URL::' => $this->getRecoveryUrl($user)
      ])
      ->setTo($user->getEmail());
      
      $this
      ->mailer
      ->send($message);
    }
  }

  protected function getInitUrl(ActiveRecordInterface $user)
  {
    $route = sprintf(
      'app_%s_security_init%s',
      $this->sectionName,
      $this->isRoutingLocalized ? '.' . ($user->getCulture() ?: 'cs') : ''
    );

    return $this->router->generate(
      $route,
      [
        'hash' => $user->getInitHash()
      ],
      UrlGeneratorInterface::ABSOLUTE_URL
    );
  }

  protected function getRecoveryUrl(ActiveRecordInterface $user)
  {
    $route = sprintf(
      'app_%s_security_recovery%s',
      $this->sectionName,
      $this->isRoutingLocalized ? '.' . ($user->getCulture() ?: 'cs') : ''
    );

    return $this->router->generate(
      $route,
      [
        'hash' => $user->getRecoveryHash()
      ],
      UrlGeneratorInterface::ABSOLUTE_URL
    );
  }
}