View Layer
==========

This Bundle provides a solution to make an output format agnostic controllers.
The idea is that all output specific logic is handled in a separate view layer.
This means there is no need to write format specific code into the controller,
making the solution more flexible and maintainable.

It should be noted once more that the main purpose of the view layer is to enable reuse
and separation of view related logic. It cannot cover all use cases for everybody.
However it should provide all required extension points to do anything view related
without having to touch the controller logic.

The view layer uses the Symfony Serializer component, which enables registering encoders
to support different formats as well as normalizers to transform objects to arrays.

Status Quo
----------

For each request in Symfony2, the goal of the developer is always the same:
to construct and return a ``Response`` object that represents the resource
being requested. This is most obvious inside a controller, which almost always
returns a ``Response`` object:

    public function indexAction($name)
    {
        // create a Response object directly
        return new Response('<html><body>Hello '.$name.'!</body></html');

        // create a Response object using the content from a template
        return new Response($this->renderView(
            'MyBundle:Mycontroller:index.html.twig',
            array('name' => $name)
        ));
    }

Using the view layer means minimal changes to the preparation of the ``Response``
object by rendering templates and performing other actions. Specifically, the view
allows the same logic to be used to create a ``Response`` whose content is HTML,
JSON, XML or any other format.

    public function indexAction($name)
    {
        return $this->handle(array('name' => $name), 'MyBundle:MyController:index.html.twig');
    }

At the surface, the ``handle()`` method simply renders the ``MyBundle:MyController:index.html.twig``
template and passes the ``$name`` variable to it. In reality, however, the
process is much more powerful.

Basic Use
---------

Basically all that is needed is to call the ``handle()`` method. However usually one will also
want to call the ``setTemplate()`` method to set a template. However by default the template is
only used if the format is ``html``. Passing parameters is done via the ``setParameters()`` method.
When the format is ``html`` the parameters will be passed to the template layer, while for
``xml`` and ``json`` the parameters serialized accordingly without going through the template layer
at all.

    <?php

    namespace MyProject\MyBundle\Controller;

    class DefaultController
    {
        /**
         * view layer
         * @var Liip\View\DefaultView
         */
        protected $view;

        /**
         * Constructor
         *
         * @param MyProject\MyBundle\View\DefaultView $view view layer
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
            $this->view->setTemplate(array('bundle' => 'MyBundle, 'controller' => Default, 'name' => index'));
            return $this->view->handle();
        }

        /**
         * Handle showing article request
         *
         * @return Symfony\Component\HttpFoundation\Response
         */
        public function viewArticleAction($articleId)
        {
            $article = $this->articleRepository->getById($articleId);
            $parameters = array(
                'article' => $article,
            );

            // Get the view service from the container or inject it in the constructor
            $view = $this->view;
            $view->setParameters($parameters);
            $this->view->setTemplate(array('bundle' => 'MyBundle, 'controller' => Default, 'name' => view'));
            return $view->handle($this->request);
        }
    }

The View with different Request Formats
---------------------------------------

The ``DefaultView`` object behaves differently based on the request format.
By default, three request formats are supported

* ``html``: The given template is rendered and its content is used to create
  and return a the ``Response`` object;

* ``json``: The parameters are transformed into a json-encoded string and
  used to create and return the ``Response`` object. See `Transforming Parameters to JSON`_;

* ``xml``: The parameters are transformed into an XML document and used to
  create and return the ``Response`` object. See `Transforming Parameters and XML`_.

Support for any number of other formats can be added (see `Custom Format Handler`_).

In our example, the three formats would be handled in the following ways:

* ``html`` ``MyBundle:MyController:index.html.twig`` is rendered;

* ``json``: The ``array('name' => $name))`` is json-encoded and the resulting
  string is used to populate the ``Response`` object;

* ``xml``: The ``array('name' => $name))`` is transformed into a simple
  XML document and used to populate the ``Response`` object.

This allows the same controller to return any number of different formats,
without needing to modify the controller code. As a developer, it also gives
you the power to choose how to process and handle specific formats on an
application-wide (`Using a Custom View`_) or controller-specific (`Custom Format Handler`_)
basis.

Configuration
-------------

Registering a custom encoder requires modifying several configuration options.
Following an example adding support for a custom RSS encoder while removing
support for xml. Also the default Json encoder class is to modified:

# app/config.yml
liip_view:
    liip_view.formats:
            rss: my.encoder.rss
            xml: false
    class:
        json: MyProject\MyBundle\Serializer\Encoder\JsonEncoder

Note the service for the RSS encoder needs to be defined in a custom bundle:
        <service id="my.encoder.rss" class="MyProject\MyBundle\Serializer\Encoder\RSSEncoder" />

Custom Format Handler
---------------------

By default, ``DefaultView`` handles three different formats: ``html``, ``json``,
and ``xml``. To override the default behavior for these formats, or to add
support for new formats, custom format handlers can be registered with the
view service. A custom handler is a PHP callback that will be invoked whenever
the view attempts to handle a specific format:

    public function indexAction($name)
    {
        $this->get('view')->registerHandler('json', array($this, 'handleJson'));

        return $this->handle(array('name' => $name), 'MyBundle:MyController:index.twig');
    }

When the request format is ``json``, the method ``handleJson`` will be called
on the controller object. Suppose that we'd like to render the
``MyBundle:MyController:index.json.twig`` template and use it to build a JSON
array:

    public function handleJson(DefaultView $view, Request $request, Response $response)
    {
        $template = $view->getTemplate();
        $template['format'] = 'json'
        $content = $this->renderView($template, $view->getParameters());
        $json = json_encode(array('content' => $content, 'timestamp' => time()));
        $response->setContent($json);

        return $response;
    }

The job of a custom handler is to prepare and return the ``Response`` object
by creating and setting content in the appropriate format. Here, we populate
the ``Response`` with a json-encoded string with the content from the template
and a timestamp that might be used by client-side Javascript.

In order to type-hint the ``DefaultView``, ``Request`` and ``Response``
objects in the ``handleJson`` method, the following would need to be
registered at the top of the controller class::

    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Bundle\FrameworkBundle\View\DefaultView;

However a cleaner and more importantly more reusable approach would be to implement
a new encoder. See the ``Liip\ViewBundle\Serializer\Encoder\HtmlEncoder.php`` for
an example of a custom encoder.

Handling Redirects
------------------

In addition to creating content, the view is also responsible for processing
redirects. Consider the following example:

    public function updateAction($slug)
    {
        // .. perform some update logic

        $this->view->setRouteRedirect('article_show', array('slug' => $slug));
        return $this->view->handle();
    }

In all formats, the default behavior is to create and return a ``Response``
object with a ``Location`` header and a 301 or 302 status code. This triggers
the default redirect behavior and directs the client's browser to redirect
to the given page.

This behavior can be controlled on a format-by-format basis. For example
this can be used to immediately resolve the controller responsible for the
redirect when redirecting to a route and return the data from said controller
without doing any redirect at all.

However for the following example let's revisit the custom handler ``handleJson``
from earlier and add some redirect logic to it:

    public function handleJson(DefaultView $view, Request $request, Response $response)
    {
        if ($redirect = $view->getRedirect()) {
            $response->setRedirect($redirect['location'], $redirect['status_code']);
            return $response;
        }

        // ... the remainder of the handling
    }

If the original action method sets a redirect via ``setRouteRedirect`` or
``setRouteUri``, the information is stored in the ``$view`` service.
In the above code, we've implemented the default redirect behavior: the
redirect is set on the ``Response`` object and returned.

Let's change the behavior and return a JSON-encoded array instead of redirecting.
This may be more advantageous if the response is being returned to client-side
Javascript code:

    public function handleJson(DefaultView $view, Request $request, Response $response)
    {
        if ($redirect = $view->getRedirect()) {
            $json = json_encode(array('redirect' => $redirect['location']));
            $response->setContent($json);

            return $response;
        }

        // ... the remainder of the handling
    }

In this case, if the request format is JSON, a JSON-encoded array will be
returned with a status code of 200. Your client-side Javascript can handle
the redirect however you choose.
