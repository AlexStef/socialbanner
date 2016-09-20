<?php

namespace Creads\SocialBanner\App\Controller;

use Silex\Application;
use Silex\Api\ControllerProviderInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Creads\SocialBanner\Common\Entity\Project;

/**
 * This ControllerProvider provides the needed routes for the payment process.
 */
class PaymentControllerProvider implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        /*
         * This is the route called by PayPlug to notify us that a payment was done
         */
        $controllers->post('/project/{projectGid}/payment/callback', function (Application $app, $projectGid) {
            $input = $app['request_stack']->getCurrentRequest()->getContent();
            try {
                // Retrieve the Resource from notification
                // As it calls PayPlug API, we can trust this resource
                $resource = \Payplug\Notification::treat($input);
                if ($resource instanceof \Payplug\Resource\Payment) {
                    // The notification is about a payment
                    if (!$resource->metadata['project_gid']) {
                        throw new BadRequestHttpException('Payment cannot be saved as Project GID was not given.');
                    }

                    // Save the paymend Id in the project
                    $project = $app['orm.em']->getRepository('Creads\SocialBanner\Common\Entity\Project')->findOneByPartnersGid($resource->metadata['project_gid']);
                    $project->setPaymentId($resource->id);
                    if ($resource->is_paid) {
                        $project->setStatus(Project::PAID_STATUS);

                        // Publish Partners project
                        $body = [
                            'state' => 'published',
                        ];
                        $res = $app['partners']->put('/v1/projects/'.$projectGid, $body);

                        if (204 !== $res->getStatusCode()) {
                            throw new \Exception('Failed to create project on partners');
                        }
                    } else {
                        $project->setStatus(Project::PAYMENT_FAILED_STATUS);
                    }
                    $project->setPaymentDate(new \DateTime('now'));
                    $app['orm.em']->persist($project);
                    $app['orm.em']->flush();

                    return new Response('Notified');
                } elseif ($resource instanceof \Payplug\Resource\Refund) {
                    // If the notification is about a refund
                    // @todo Process the refund.
                    return new Response('Notified refund');
                }
            } catch (\Payplug\Exception\PayplugException $exception) {
                // Handle errors
                throw new \Exception('not paid');
            }
            // Unexpected case
            throw new NotFoundHttpException('Not found');
        })
        ->bind('payment_callback');

        /*
         * This route is called at submission of the payment request, to redirect to PayPlug payment page
         */
        $controllers->post('/project/{projectGid}/payment/proceed', function (Application $app, $projectGid) {
            $amount = $app['request_stack']->getCurrentRequest()->get('amount');
            if (!$amount) {
                throw new BadRequestHttpException('Invalid amount provided.');
            }
            $project = $app['orm.em']->getRepository('Creads\SocialBanner\Common\Entity\Project')->findOneByPartnersGid($projectGid);
            if (!$project) {
                throw new NotFoundHttpException(sprintf('Project %s does not exist', $projectGid));
            }
            $project->setStatus(Project::WAITING_FOR_PAYMENT_STATUS);
            $app['orm.em']->persist($project);
            $app['orm.em']->flush();

            $email = isset($app['user']) ? $app['user']->getEmail() : null;
            $firstname = isset($app['user']) ? $app['user']->getFirstname() : null;
            $lastname = isset($app['user']) ? $app['user']->getLastname() : null;

            try {
                $payment = $app['new_payment']($projectGid, $amount, $email, $firstname, $lastname);
            } catch (\Payplug\Exception\ConnectionException $e) {
                $app['session']->getFlashBag()->clear();
                $app['session']->getFlashBag()->add('danger', [
                    'message' => $app['translator']->trans('alert_payplug_error_msg'),
                ]);

                return $app->redirect($app['url_generator']->generate('payment_ask', ['projectGid' => $project->getPartnersGid()]));
            }

            return $app->redirect($payment->hosted_payment->payment_url);
        })
        ->bind('payment_proceed');

        /*
         * Payplug redirects the user to this route after the payment is done
         */
        $controllers->get('/project/{projectGid}/payment/return', function (Application $app, $projectGid) {
            $project = $app['orm.em']->getRepository('Creads\SocialBanner\Common\Entity\Project')->findOneByPartnersGid($projectGid);
            if (!$project) {
                throw new NotFoundHttpException(sprintf('Project %s does not exist', $projectGid));
            }
            $app['session']->getFlashBag()->clear();
            $app['session']->getFlashBag()->add('success', [
                'title' => $app['translator']->trans('alert_payment_success'),
                'message' => $app['translator']->trans('alert_payment_success_msg'),
            ]);

            return $app->redirect($app['url_generator']->generate('app_order_list', ['hl' => $project->getPartnersGid()]));
        })
        ->bind('payment_return');

        /*
         * Payplug redirects the user to this route after the payment is canceled
         */
        $controllers->get('/project/{projectGid}/payment/canceled', function (Application $app, $projectGid) {

            $project = $app['orm.em']->getRepository('Creads\SocialBanner\Common\Entity\Project')->findOneByPartnersGid($projectGid);
            if (!$project) {
                throw new NotFoundHttpException(sprintf('Project %s does not exist', $projectGid));
            }
            $project->setStatus(Project::NOT_PAID_STATUS);
            $app['orm.em']->persist($project);
            $app['orm.em']->flush();

            $app['session']->getFlashBag()->add('danger', [
                'title' => $app['translator']->trans('alert_payment_cancel'),
                'message' => $app['translator']->trans('alert_payment_cancel_msg', [
                    '%link%' => $app['url_generator']->generate('payment_ask', ['projectGid' => $project->getPartnersGid()]),
                ]),
            ]);

            return $app->redirect($app['url_generator']->generate('app_order_list'));
        })
        ->bind('payment_cancel');

        return $controllers;
    }
}
