View Layer
==========

This Bundle provides a

Basic use
---------

    class FooController
    {
        public function viewArticleAction($articleId)
        {
            $article = $this->articleRepository->getById($articleId);
            $parameters = array(
                'article' => $article,
            );

            // beginner
            return $this->handle($parameters, 'FooBundle:Foo:view.twig');

            // advanced
            $view = $this->view;
            $view->setParameters($parameters);
            $view->setTemplate('FooBundle:Foo:view.twig');
            return $view->handle($this->request);
        }
    }

Custom Callbacks
----------------


Example custom handler, note this could also be defined by extending the DefaultView class:

    class FooController
    {
        public function rssFeedAction()
        {
            // Could be done via DIC config
            $this->view->registerHandler('rss', array($this, 'handleRss'));

            $data = array('news' => $this->newsRepository->getLatestNews());

            // Could be done in the route
            $this->request->setRequestFormat('rss');

            $this->view->setParameters($data);
            return $this->view->handle($this->request);
        }

        public function handleRss($view, $request, $response)
        {
            $data = $view->getParameters();

            foreach ($data['news'] as $news) {
                // build feed content
            }

            $response->headers->set('Content-Type', 'text/xml');
            $response->setContent($feed);
            return $response;
        }
    }


Global Parameters
-----------------

Setting static parameters in the service definition

    services:
        fooView:
            class: Application\fooBundle\View\fooView
            arguments:
                container: @service_container
                params:
                    yuiCDN: %foo.yuiCDN%
                    yuiFilter: %foo.yuiFilter%
                    yuiModules: %foo.yuiModules%
                    cssURL: %foo.cssURL%
                    active_tab: ''
            shared: true
        fooDefault:
            class: Application\fooBundle\Controller\DefaultController
            arguments:
                view: @fooView

Setting dynamic parameters by extending the base class

    <?php

    namespace Application\fooBundle\View;

    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpFoundation\Request;

    class fooView extends \Bundle\Liip\ViewBundle\View\DefaultView
    {
        /**
        Html parameter transformer
         *
        Merges the global parameters with the given parameters and then
        renders the given template
         *
        @param Request $request
        @param Response $response
        @param array $parameters
         *
        @return string
         */
        protected function transformHtml(Request $request, Response $response, array $parameters)
        {
            $parameters['user'] = $this->container->get('security.context')->getUser();
            $parameters['csrf_token'] = hash('md5', $this->container->getParameter('csrf_secret').session_id());
            $parameters['debug'] = $this->container->getParameter('kernel.debug');

            return parent::transformHtml($request, $response, $parameters);
        }
    }

    <?php

    namespace Application\fooBundle\Controller;

    class DefaultController
    {
        /**
         * view layer
         * @var Application\fooBundle\View\DefaultView
         */
        protected $view;

        /**
         * Constructor
         *
         * @param Application\fooBundle\View\DefaultView $view view layer
         */
        public function __construct($view)
        {
            $this->view = $view;
        }

        /**
         * Handle the index request
         *
         * @return Symfony\Component\HttpFoundation\Response
         */
        public function indexAction()
        {
            $this->view->setTemplate('fooBundle:Default:index.twig');
            return $this->view->handle();
        }
    }
