<?php

namespace Creads\SocialBanner\Common\Partners;

use Pimple\Container;
use Silex\Application;
use Pimple\ServiceProviderInterface;
// use Silex\ServiceProviderInterface;
use Silex\Api\BootableProviderInterface;
use Creads\Partners\Client as PartnersClient;
use Creads\Partners\OAuthAccessToken;

class PartnersApiProvider implements ServiceProviderInterface, BootableProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function register(Container $app)
    {
        $app['partners.config.connect'] = function () use ($app) {
            $config = [];

            if (isset($app['partners.connect_uri']) && $app['partners.connect_uri']) {
                // Ensure the uri ends with a slash, else guzzle would remove /v1
                $app['partners.connect_uri'] = rtrim($app['partners.connect_uri'], '/').'/';
                $config['base_uri'] = $app['partners.connect_uri'];
            }

            return $config;
        };

        $app['partners.config.api'] = function () use ($app) {
            $config = [];
            if (isset($app['partners.base_uri']) && $app['partners.base_uri']) {
                // Ensure the uri ends with a slash, else guzzle would remove /v1
                $app['partners.base_uri'] = rtrim($app['partners.base_uri'], '/').'/';

                $config['base_uri'] = $app['partners.base_uri'];
            }

            return $config;
        };

        $app['partners'] = function () use ($app) {

            $authentication = new OAuthAccessToken($app['partners.app_id'], $app['partners.app_secret'], $app['partners.config.connect']);

            $client = new PartnersClient($authentication, $app['partners.config.api']);

            return $client;
        };
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Application $app)
    {
        if (!isset($app['partners.app_id'])) {
            throw new \LogicException('You must define \'partners.app_id\' parameter');
        }

        if (!isset($app['partners.app_secret'])) {
            throw new \LogicException('You must define \'partners.app_secret\' parameter');
        }
    }
}
