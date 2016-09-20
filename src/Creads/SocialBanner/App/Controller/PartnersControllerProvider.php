<?php

namespace Creads\SocialBanner\App\Controller;

use Silex\Application;
use Silex\Api\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PartnersControllerProvider implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        $controllers->post('/file-upload', function (Request $request, Application $app) {

            if (!$app['security.authorization_checker']->isGranted('IS_AUTHENTICATED_FULLY')) {
                throw new NotFoundHttpException('Resource not found');
            }
            $theFile = $request->files->get('file');

            $res = $app['partners']->request(
                'POST',
                'files',
                [
                    'multipart' => [
                        [
                            'name' => 'file',
                            'contents' => fopen($theFile->getPathname(), 'r'),
                            'filename' => $theFile->getClientOriginalName(),
                        ],
                        [
                            'name' => 'filepath',
                            'contents' => '/'.$theFile->getClientOriginalName(),
                        ],
                        [
                            'name' => 'organization.gid',
                            'contents' => $app['partners_api']['organization_gid'],
                        ],
                    ],
                ]
            );

            if ($res->getStatusCode() > 399) {
                $response = new Response($app['translator']->trans('dropzone_server_error'), $res->getStatusCode(), $res->getHeaders());
                $response->headers->set('Content-Type', 'text/plain');
            } else {
                $response = new Response($res->getBody(), $res->getStatusCode(), $res->getHeaders());
            }

            return $response;
        })
        ->bind('partners_upload_file');

        return $controllers;
    }
}
