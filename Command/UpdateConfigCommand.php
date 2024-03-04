<?php
namespace Fontai\Bundle\SecurityBundle\Command;

use App\Model;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;


class UpdateConfigCommand extends Command
{
  protected $projectDir;
  protected $sections;

  public function __construct(
    string $projectDir,
    array $sections
  )
  {
    $this->projectDir = $projectDir;
    $this->sections = $sections;

    parent::__construct();
  }

  protected function configure()
  {
    $this
    ->setDescription('Updates Symfony Security Bundle configuration.')
    ->setHelp('This command updates Symfony Security Bundle configuration according to sections defined by Fontai Security bundle configuration.');
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $path = sprintf('%s/config/packages/security.yaml', $this->projectDir);
    $config = Yaml::parseFile($path);

    $config['security']['access_decision_manager'] = array_replace_recursive(
      $config['security']['access_decision_manager'] ?? [],
      [
        'strategy' => 'unanimous'
      ]
    );

    if (isset($config['security']['providers']))
    {
      unset($config['security']['providers']);
    }

    if (isset($config['security']['firewalls']['main']))
    {
      unset($config['security']['firewalls']['main']);
    }

    if (!isset($config['security']['access_control']))
    {
      $config['security']['access_control'] = [];
    }

    $accessControlRules = [];

    foreach ($this->sections as $sectionName => $params)
    {
      $loginPath = sprintf('app_%s_security_login', $sectionName);

      $accessControlRules[] = [
        'path' => sprintf('^%s/(login|logout|init/[a-f0-9]+|forgotten-password(/[a-f0-9]+)?)', $params['route_prefix']),
        'roles' => 'IS_AUTHENTICATED_ANONYMOUSLY',
        'requires_channel' => $params['requires_channel']
      ];
      
      $accessControlRules[] = [
        'path' => sprintf('^%s/?', $params['route_prefix']),
        'roles' => $params['role'],
        'requires_channel' => $params['requires_channel']
      ];

      $formLoginName = $params['localized_routing'] ?? FALSE ? 'form_localized_login' : 'form_login';

      $optionsFormLogin = [
        'login_path' => $loginPath,
        'check_path' => $loginPath,
        'default_target_path' => $params['login_target'],
        'use_forward' => TRUE,
        'post_only' => TRUE,
        'username_parameter' => 'login[email]',
        'password_parameter' => 'login[password]',
        'csrf_token_generator' => 'security.csrf.token_manager'
      ];

      $initHashLoginName = $params['localized_routing'] ?? FALSE ? 'init_hash_localized_login' : 'init_hash_login';

      $optionsInitHashLogin = [
        'check_path' => sprintf('app_%s_security_init', $sectionName),
        'default_target_path' => $params['init_target'],
        'always_use_default_target_path' => TRUE,
        'failure_path' => $loginPath,
        'query' => sprintf('%sQuery', $params['entity_user']),
      ];

      if ($params['localized_routing'] ?? FALSE)
      {
        $locales = Model\LanguageQuery::create()
        ->select('Code')
        ->filterByIsFrontendActive(TRUE)
        ->find()
        ->getData();

        $locales = implode(',', $locales);

        $optionsFormLogin['route_locale'] = $locales;
        $optionsInitHashLogin['route_locale'] = $locales;
      }

      $config['security']['firewalls'][$sectionName] = [
        'pattern' => sprintf('^%s/?', $params['route_prefix']),
        'provider' => $sectionName,
        'user_checker' => sprintf('fontai_security.user_checker.%s', $sectionName),
        'anonymous' => [
          'secret' => NULL
        ],
        'access_denied_url' => $loginPath,
        $initHashLoginName => $optionsInitHashLogin,
        $formLoginName => $optionsFormLogin,
        'logout' => [
          'path' => sprintf('app_%s_security_logout', $sectionName),
          'target' => $params['logout_target'] ?? $loginPath
        ]
      ];

      if (isset($params['success_handler']))
      {
        $config['security']['firewalls'][$sectionName][$formLoginName]['success_handler'] = $params['success_handler'];
      }

      if (isset($params['failure_handler']))
      {
        $config['security']['firewalls'][$sectionName][$formLoginName]['failure_handler'] = $params['failure_handler'];
      }

      if ($params['subdomain'] ?? NULL)
      {
        $config['security']['firewalls'][$sectionName]['host'] = sprintf('^(%s)\..+', implode('|', $params['subdomain']));
      }
    }

    $config['security']['access_control'] = $this->getAccessControlRules(
      $config['security']['access_control'] ?? [],
      $accessControlRules
    );

    file_put_contents($path, Yaml::dump($config, 5, 4));

    return 0;
  }

  protected function getAccessControlRules($rulesOld, $rulesNew)
  {
    $rulesNew = array_reverse($rulesNew);
    
    foreach ($rulesNew as $ruleNew)
    {
      foreach ($rulesOld as $i => $ruleOld)
      {
        if ($ruleOld['path'] == $ruleNew['path'])
        {
          unset($rulesOld[$i]);
        }
      }

      array_unshift($rulesOld, $ruleNew);
    }

    return array_values($rulesOld);
  }
}