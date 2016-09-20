<?php

namespace Creads\SocialBanner\Common\Payment;

use Silex\Application;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Api\BootableProviderInterface;

/**
 * This ServiceProvides provides methods to manipulate Payments (in our case, via PayPlug API).
 */
class PaymentServiceProvider implements ServiceProviderInterface, BootableProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function register(Container $app)
    {
        // Generates a PayPlug payment for a given project and amount
        $app['new_payment'] = $app->protect(function ($projectGid, $amount, $email = null, $firstname = null, $lastname = null) use ($app) {

            $baseUri = $app['request_stack']->getCurrentRequest()->getSchemeAndHttpHost();

            if (floatval($amount) <= 0) {
                throw new \InvalidArgumentException(sprintf('The given amount for payment could not be resolved to a positive number.'));
            }
            $amountInCents = 100 * floatval($amount);

            $payment = \Payplug\Payment::create([
                'amount' => intval($amountInCents),
                'currency' => $app['payment']['currency'],
                'customer' => [
                    'email' => $email,
                    'first_name' => $firstname,
                    'last_name' => $lastname,
                ],
                'save_card' => false,
                'hosted_payment' => array(
                    'return_url' => $baseUri.$app['url_generator']->generate('payment_return', ['projectGid' => $projectGid]),
                    'cancel_url' => $baseUri.$app['url_generator']->generate('payment_cancel', ['projectGid' => $projectGid]),
                ),
                'notification_url' => $baseUri.$app['url_generator']->generate('payment_callback', ['projectGid' => $projectGid]),
                'metadata' => array(
                    'project_gid' => $projectGid,
                ),
            ]);

            return $payment;
        });

        // Retrieves a payment from its Id
        $app['get_payment'] = $app->protect(function ($paymentId) use ($app) {

            $payment = \Payplug\Payment::retrieve($paymentId);

            return $payment;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Application $app)
    {
    }
}
