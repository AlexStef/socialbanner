<?php

$app = new Silex\Application();

$app['env'] = getenv('APP_ENV') ?: 'dev';

if (PHP_SAPI === 'cli') {
    $app->register(new Ivoba\Silex\Provider\ConsoleServiceProvider(), array(
        'console.name' => 'console',
        'console.version' => '1.0',
        'console.project_directory' => __DIR__,
    ));
    $app['console']->getDefinition()->addOption(new Symfony\Component\Console\Input\InputOption('--env', '-e', Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED, 'The Environment name.', 'dev'));

    $input = new Symfony\Component\Console\Input\ArgvInput();
    $app['env'] = $input->getParameterOption(array('--env', '-e'), $app['env']);
}

$app['root_dir'] = __DIR__.'/../web';
$app['cache_dir'] = __DIR__.'/cache/'.$app['env'];

// LOAD CONFIGURATION
$app->register(new Igorw\Silex\ConfigServiceProvider(__DIR__.'/config/config.yml'));
if (file_exists(__DIR__."/config/config_{$app['env']}.yml")) {
    $app->register(new Igorw\Silex\ConfigServiceProvider(__DIR__.'/config/config_'.$app['env'].'.yml'));
}

// CONTROLLER
$app->register(new Silex\Provider\ServiceControllerServiceProvider());

// DOCTRINE
// fixup path configuration
$config = $app['db.options'];
if ($config['path']) {
    $config['path'] = __DIR__.'/'.ltrim($config['path'], '/');
}
$app->register(new Silex\Provider\DoctrineServiceProvider(), array('db.options' => $config));

// TRANSLATION
$app->register(new Silex\Provider\LocaleServiceProvider());
$app->register(new Silex\Provider\TranslationServiceProvider(), array(
    'locale' => 'fr',
    'locale_fallbacks' => ['en'],
    'translator.messages' => [],
));

$app->extend('translator', function ($translator, $app) {
    $translator->addLoader('yaml', new Symfony\Component\Translation\Loader\YamlFileLoader());
    $translator->addResource('yaml', __DIR__.'/trans/fr.yml', 'fr');
    $translator->addResource('yaml', __DIR__.'/trans/en.yml', 'en');

    $translator->addResource('yaml', __DIR__.'/trans/date.fr.yml', 'fr', 'date');
    $translator->addResource('yaml', __DIR__.'/trans/date.en.yml', 'en', 'date');

    return $translator;
});

// TWIG
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => array(
        __DIR__.'/views/',
    ),
    'twig.options' => array(
        'cache' => $app['cache_dir'].'/twig',
        'debug' => $app['debug'],
    ),
));

$app->extend('twig', function (Twig_Environment $twig, $app) {
    $twig->addExtension(new Twig_Extensions_Extension_Text());
    $twig->addExtension(new Twig_Extensions_Extension_Date($app['translator']));
    $twig->addFilter(new Twig_SimpleFilter('url_decode', function ($value) {
        return urldecode($value);
    }));

    return $twig;
});

// VALIDATOR
$app->register(new Silex\Provider\ValidatorServiceProvider());

// FORM
$app->register(new Silex\Provider\CsrfServiceProvider());
$app->register(new Silex\Provider\FormServiceProvider());

// SESSION
$app->register(new Silex\Provider\SessionServiceProvider());

// CREADS SERVICES
// $app->register(new Creads\SAAS\Provider\FormServiceProvider());

$app->register(new Silex\Provider\SecurityServiceProvider(), array(
    'security.firewalls' => array(
        'default' => array(
            'pattern' => '^/',
            'anonymous' => true,
            'oauth' => array(
                // to be override those default routes
                // 'login_path' => '/auth/{service}',
                // 'callback_path' => '/auth/{service}/callback',
                // 'check_path' => '/auth/{service}/check',
                'failure_path' => '/login',
                'with_csrf' => true,
            ),
            'logout' => array(
                'logout_path' => '/logout',
                // 'with_csrf' => false,
            ),
            'users' => function () use ($app) {
                return new Creads\SocialBanner\Common\Facebook\FacebookUserProvider($app['orm.em']);
            },
        ),
    ),
    'security.access_rules' => array(
        array('^/order/*', 'IS_AUTHENTICATED_FULLY'),
        array('^/order$', 'IS_AUTHENTICATED_FULLY'),
        array('^/orders$', 'IS_AUTHENTICATED_FULLY'),
        array('^/', 'IS_AUTHENTICATED_ANONYMOUSLY', $app['require_channel']),
    ),
));

// URL GENERATOR
// $app->register(new Silex\Provider\UrlGeneratorServiceProvider());

// Doctrine ORM
$app->register(new Dflydev\Provider\DoctrineOrm\DoctrineOrmServiceProvider(), array(
    'orm.proxies_dir' => $app['cache_dir'].'/proxies',
    'orm.em.options' => array(
        'mappings' => array(
            array(
                'type' => 'annotation',
                'namespace' => 'Creads\SocialBanner\Common\Entity',
                'path' => __DIR__.'/../src/Creads/SocialBanner/Common/Entity',
                'use_simple_annotation_reader' => false,
            ),
        ),
    ),
));

$app->register(new Gigablah\Silex\OAuth\OAuthServiceProvider(), array(
    'oauth.services' => array(
        'Facebook' => array(
            'key' => $app['facebook']['key'],
            'secret' => $app['facebook']['secret'],
            'scope' => array('public_profile', 'email'),
            'user_endpoint' => 'https://graph.facebook.com/me?fields=id,name,email',
        ),
    ),
));

$app->register(new Creads\SocialBanner\Common\Partners\PartnersApiProvider(), [
    'partners.app_id' => $app['partners_api']['app_id'],
    'partners.app_secret' => $app['partners_api']['app_secret'],
    'partners.base_uri' => isset($app['partners_api']['base_uri']) ? $app['partners_api']['base_uri'] : null,
    'partners.connect_uri' => isset($app['partners_api']['connect_uri']) ? $app['partners_api']['connect_uri'] : null,
]);

\Payplug\Payplug::setSecretKey($app['payment']['payplug_key']);
$app->register(new Creads\SocialBanner\Common\Payment\PaymentServiceProvider());

//your custom providers here
//...

if (PHP_SAPI === 'cli') {
    $helperSet = $app['console']->getHelperSet();
    $helperSet->set(new Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper($app['db']), 'db');
    $helperSet->set(new Doctrine\ORM\Tools\Console\Helper\EntityManagerHelper($app['orm.em']), 'em');

    $app['console']->add(new Doctrine\ORM\Tools\Console\Command\SchemaTool\CreateCommand());
    $app['console']->add(new Doctrine\ORM\Tools\Console\Command\SchemaTool\UpdateCommand());
    $app['console']->add(new Doctrine\ORM\Tools\Console\Command\SchemaTool\DropCommand());

    //your custom commands here
    $app['console']->add(new Creads\SocialBanner\Common\Command\PasswordEncodeAppCommand());
    $app['console']->add(new Saxulum\DoctrineOrmCommands\Command\CreateDatabaseDoctrineCommand());
    $app['console']->add(new Saxulum\DoctrineOrmCommands\Command\DropDatabaseDoctrineCommand());

    $app['console']->getHelperSet()->set(new Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper($app['db']), 'doctrine');
} else {
    $app->mount('', new Creads\SocialBanner\App\Controller\DefaultControllerProvider());
    $app->mount('', new Creads\SocialBanner\App\Controller\PaymentControllerProvider());
}

return $app;
