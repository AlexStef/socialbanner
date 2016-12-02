<?php

namespace Creads\SocialBanner\App\Controller;

use Silex\Application;
use Silex\Api\ControllerProviderInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\Request;
use Creads\SocialBanner\Common\Entity\Project;
use Creads\SocialBanner\App\Form\CommentFormType;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DefaultControllerProvider implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        $app->before(function (Request $request, Application $app) {

            /*
             * This middleware is used to display alert to the user about
             * his failed payments, whatever the page
             */

            $currentUser = $app['security.token_storage']->getToken()->getUser();
            if ($currentUser && $app['security.authorization_checker']->isGranted('IS_AUTHENTICATED_FULLY')) {
                $aWeekAgo = new \DateTime();
                $aWeekAgo->modify('- 7 days');
                $failedProjects = $app['orm.em']->getRepository('Creads\SocialBanner\Common\Entity\Project')->findByUserAndPaymentFailedSinceDate($currentUser, $aWeekAgo);

                foreach ($failedProjects as $key => $project) {
                    $app['session']->getFlashBag()->add('payment', [
                        'title' => $app['translator']->trans('alert_payment_error'),
                        'message' => $app['translator']->trans('alert_payment_error_msg', ['%link%' => $app['url_generator']->generate('payment_ask', ['projectGid' => $project->getPartnersGid()])]),
                    ]);
                }
            }
        });

        $controllers->get('/', function (Application $app) {

            return $app['twig']->render('App/Default/home.html.twig');
        })
        ->bind('app_home');

        // GET and POST routes for the Order form that generates a new Project
        $controllers->match('/order/{productId}',  function (Request $request, $productId) use ($app) {
            if (!isset($app['partners_api']['default_projects'][$productId])) {
                throw new NotFoundHttpException('Product does not exist');
            }
            $owner = $app['security.token_storage']->getToken()->getUser();

            // Build the brief form
            $form = $app['form.factory']->createBuilder(FormType::class)
            ->add('title', TextType::class, array(
                'constraints' => array(new Assert\NotBlank(), new Assert\Length(array('min' => 5)), new Assert\Length(array('max' => 80))),
                'required' => true,
                'attr' => array('class' => 'form-control', 'placeholder' => 'Titre du projet'),
                 ))
            ->add('brief', TextAreaType::class, array(
                'constraints' => array(new Assert\NotBlank(), new Assert\Length(array('min' => 5))),
                'required' => false,
                'attr' => array('class' => 'form-control', 'rows' => 12),
                 ))
            ->add('files', HiddenType::class, ['data' => '[]'])
            ->getForm();

            $form->handleRequest($request);

            if ($form->isValid()) {
                $data = $form->getData();

                // Build the body of the request to Partners API
                // From the form data and configured valued for a project
                $body = array_merge($app['partners_api']['default_projects'][$productId], [
                        'title' => $data['title'],
                        // Concatenate standard description of product with user input
                        'description' => $app['partners_api']['default_projects'][$productId]['description'].$data['brief'],
                    ]
                );
                // If brief files have been added, join them to the body
                $files = json_decode($data['files'], true);
                if (count($files)) {
                    $body['brief_files'] = $files;
                }

                // Send new project request to the Partners API
                $res = $app['partners']->post('projects', $body);

                // The Location header contains the URI of the created resource
                if (!$res->getHeader('Location')[0] || !is_string($res->getHeader('Location')[0])) {
                    throw new \Exception(sprintf('Location from partners could not be parsed (%s).', gettype($res->getHeader('Location')[0])));
                }

                // Extract the Partners GID from the Location header
                $gid = str_replace('/v1/projects/', '', $res->getHeader('Location')[0]);

                // Store the new project linked to our user
                $project = new Project();
                $project->setUser($owner);
                $project->setPartnersGid($gid);
                $project->setCreatedAt(new \DateTime('now'));

                $app['orm.em']->persist($project);
                $app['orm.em']->flush();

                return $app->redirect($app['url_generator']->generate('payment_ask', ['projectGid' => $gid]));
            }

             // display the form
             return $app['twig']->render('App/Default/order.html.twig', array('form' => $form->createView(), 'productId' => $productId));
        })
        ->bind('app_order');

        // The route to view a project
        $controllers->get('/order/view/{id}', function (Application $app, $id) {
            $owner = $app['security.token_storage']->getToken()->getUser();

            // Get the project
            $project = $app['orm.em']->getRepository('Creads\SocialBanner\Common\Entity\Project')->findOneById($id);
            if (!$project || $project->getUser() !== $owner) {
                throw new NotFoundHttpException('Could not find Project in user\'s projects.');
            }
            $partnersProject = $app['partners']->get('projects/'.$project->getPartnersGid());

            // Get the works
            $workQuery = ['project.gid', '==', $partnersProject['gid']];
            $worksResponse = $app['partners']->get('works?query='.json_encode($workQuery));
            $works = $worksResponse['items'];

            // Get the comments
            $query = ['uri', '==', $partnersProject['href']];
            $commentsResponse = $app['partners']->get('comments?orderBy=created_at&sort=asc&query='.json_encode($query));
            $comments = $commentsResponse['items'];

            // build the comment form
            $form = $app['form.factory']->create(new CommentFormType(), ['projectUri' => $partnersProject['href']]);

            return $app['twig']->render('App/Default/order_view.html.twig',
                [
                    'project' => $partnersProject,
                    'comments' => $comments,
                    'commentForm' => $form->createView(),
                    'works' => $works,
                    'imgBaseUrl' => $app['partners_api']['base_uri'].'img',
                    'fileBaseUrl' => $app['partners_api']['base_uri'].'dl',
                    'fileToken' => $app['partners.public_token'],
                    'needsActivation' => !$project->isPaid(),
                ]
            );
        })
        ->bind('app_order_view');

        // Lists all the user's projects
        $controllers->get('/orders', function (Request $request, Application $app) {
            $owner = $app['security.token_storage']->getToken()->getUser();
            $projects = $app['orm.em']->getRepository('Creads\SocialBanner\Common\Entity\Project')->findByUser($owner);

            $projectsMap = [];

            // We need to build a Partners Query to get all the Projects which Gids are stored for this user
            // We're going to build a query using 'in' operator: ['gid', 'in', [ <GID1>, <GID2> , <GID3>, ...  ]]
            $query = ['gid', 'in', []];
            foreach ($projects as $key => $project) {
                $query[2][] = $project->getPartnersGid();
                $projectsMap[$project->getPartnersGid()] = $project;
            }

            if (count($projects)) {
                $projectsResponse = $app['partners']->get('projects?query='.json_encode($query).'&orderBy=created_at&sort=desc');
                $partnersProjects = $projectsResponse['items'];
            } else {
                $partnersProjects = [];
            }

            $highlighted = $request->query->get('hl');

            return $app['twig']->render('App/Default/order_list.html.twig', ['projects' => $partnersProjects, 'projectsMap' => $projectsMap, 'highlighted' => $highlighted]);
        })
        ->bind('app_order_list');

        // The login route
        $controllers->get('/login', function (Application $app) use ($app) {
            $services = array_keys($app['oauth.services']);

            return $app['twig']->render('App/Default/login.html.twig', array(
                'login_paths' => $app['oauth.login_paths'],
                'error' => $app['security.last_error']($app['request_stack']->getCurrentRequest()),
            ));
        })
        ->bind('login');

        // Endpoint for the user to post a comment on one of his projects
        $controllers->post('/project/{projectGid}/comment', function (Application $app, $projectGid) {
            $owner = $app['security.token_storage']->getToken()->getUser();
            $project = $app['orm.em']->getRepository('Creads\SocialBanner\Common\Entity\Project')->findOneByPartnersGid($projectGid);
            $form = $app['form.factory']->create(new CommentFormType());

            $form->handleRequest($app['request_stack']->getCurrentRequest());

            if ($project && $project->getUser() === $owner && $form->isValid()) {
                $data = $form->getData();
                $body = [
                    'message' => $data['message'],
                    'uri' => $data['projectUri'],
                ];

                // Send new comment request to the Partners API
                $res = $app['partners']->post('comments', $body);

                if (201 === $res->getStatusCode()) {
                    return $app->redirect($app['url_generator']->generate('app_order_view', ['id' => $project->getId()]));
                }
            }
            $app['session']->getFlashBag()->add('danger', [
                'title' => $app['translator']->trans('alert_oops'),
                'message' => $app['translator']->trans('alert_comment_error'),
            ]);

            return $app->redirect($app['url_generator']->generate('app_order_view', ['id' => $project->getId()]));
        })
        ->bind('app_project_comment');

        // Route to select a winner to a project
        $controllers->post('/project/{projectGid}/winner', function (Application $app, $projectGid) {
            $owner = $app['security.token_storage']->getToken()->getUser();
            $project = $app['orm.em']->getRepository('Creads\SocialBanner\Common\Entity\Project')->findOneByPartnersGid($projectGid);
            if ($project && $project->getUser() === $owner && $app['request_stack']->getCurrentRequest()->get('winner_gid')) {
                $body = [
                    'winner' => [
                        'gid' => $app['request_stack']->getCurrentRequest()->get('winner_gid'),
                    ],
                ];

                // Send project modification request to the Partners API
                $res = $app['partners']->put('projects/'.$projectGid, $body);
                if (204 === $res->getStatusCode()) {
                    $app['session']->getFlashBag()->add('success', [
                        'title' => $app['translator']->trans('alert_bravo'),
                        'message' => $app['translator']->trans('alert_winner_chosen'),
                    ]);

                    return $app->redirect($app['url_generator']->generate('app_order_view', ['id' => $project->getId()]));
                }
            }

            $app['session']->getFlashBag()->add('danger', [
                'title' => $app['translator']->trans('alert_oops'),
                'message' => $app['translator']->trans('alert_winner_error'),
            ]);

            return $app->redirect($app['url_generator']->generate('app_order_view', ['id' => $project->getId()]));
        })
        ->bind('app_winner_select');

        // Route to display a link towards external Payment page
        $controllers->get('/project/{projectGid}/payment/ask', function (Application $app, $projectGid) {
            $project = $app['orm.em']->getRepository('Creads\SocialBanner\Common\Entity\Project')->findOneByPartnersGid($projectGid);
            $owner = $app['security.token_storage']->getToken()->getUser();
            if (!$project || $project->getUser() !== $owner || true === $project->isPaid()) {
                throw new NotFoundHttpException('Project does not exist');
            }
            $partnersProject = $app['partners']->get('projects/'.$projectGid);
            if (!isset($partnersProject['gid'])) {
                throw new NotFoundHttpException('Project does not exist');
            }

            return $app['twig']->render('App/Default/order_confirm.html.twig', [
                'payment_url' => $app['url_generator']->generate('payment_proceed', ['projectGid' => $projectGid]),
                'payment_amount' => $partnersProject['price']['amount'],
                'partnersProject' => $partnersProject,
            ]);
        })
        ->bind('payment_ask');

        return $controllers;
    }
}
