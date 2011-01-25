<?php

namespace Liip\ViewBundle\View;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Symfony\Component\Serializer\Encoder\TemplatingAwareEncoderInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * DefaultView is a default view implementation
 *
 * Use it in controllers to build up a response in a format agnostic way
 * The View class takes care of encoding your data in json, xml, or renders a template for html.
 *
 * It is of course extensible and overrideable to provide a very flexible solution
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Lukas K. Smith <smith@pooteeweet.org>
 */
class DefaultView
{
    protected $container;
    protected $globalParameters;
    protected $customHandlers = array();
    protected $serializer;

    protected $redirect;
    protected $template;
    protected $format;
    protected $parameters;
    protected $renderer;

    /**
     * Constructor
     *
     * Takes an optional array of global parameters, those will be merged with the
     * normal parameters before rendering an html template, which makes them useful for
     * data that you need to have accessible in the layout for every page on your site.
     *
     * @param ContainerInterface $container The service_container service.
     * @param array $globalParameters optional global array of parameters for html templates
     */
    public function __construct(ContainerInterface $container, array $globalParameters = array())
    {
        $this->reset();
        $this->container = $container;
        $this->globalParameters = $globalParameters;
    }

    /**
     * Registers a custom handler
     *
     * The handler must have the following signature: handler($viewObject, $request, $response)
     * It can use the public methods of this class to retrieve the needed data and return a
     * Response object ready to be sent.
     *
     * @param string $format the format that is handled
     * @param callback $callback handler callback
     */
    public function registerHandler($format, $callback)
    {
        $this->customHandlers[$format] = $callback;
    }

    /**
     * Sets a redirect using a route and parameters
     *
     * @param string $route route name
     * @param array $parameters route parameters
     * @param int $code optional http status code
     */
    public function setRouteRedirect($route, array $parameters = array(), $code = 302)
    {
        $this->redirect = array(
            'location' => $this->container->get('router')->generate($route, $parameters),
            'status_code' => $code,
        );
    }

    /**
     * Sets a redirect using an URI
     *
     * @param string $uri URI
     * @param int $code optional http status code
     */
    public function setUriRedirect($uri, $code = 302)
    {
        $this->redirect = array('location' => $uri, 'status_code' => $code);
    }

    public function getRedirect()
    {
        return $this->redirect;
    }

    public function setParameters($parameters)
    {
        $this->parameters = $parameters;
    }

    public function getParameters()
    {
        return $this->parameters;
    }

    public function setTemplate(array $template)
    {
        $this->template = $template;
    }

    public function getTemplate()
    {
        if (null === $this->template) {
            return null;
        }

        $template = $this->template;

        if (empty($template['format'])) {
            $template['format'] = $this->getFormat();
        }

        if (empty($template['renderer'])) {
            $template['renderer'] = $this->getRenderer();
        }

        // TODO in theory we should be able to override the default TemplateNameParser to handle pre-parsed template names
        // <parameter key="templating.name_parser.class">Liip\ViewBundle\Templating\TemplateNameParser</parameter>
        return $template['bundle'].':'.$template['controller'].':'.$template['name'].'.'.$template['format'].'.'.$template['renderer'];
    }

    public function setRenderer($renderer)
    {
        $this->renderer = $renderer;
    }

    public function getRenderer()
    {
        return $this->renderer;
    }

    public function setFormat($format)
    {
        $this->format = $format;
    }

    public function getFormat()
    {
        return $this->format;
    }

    public function setGlobalParameters(array $globalParameters)
    {
        $this->globalParameters = $globalParameters;
    }

    public function getGlobalParameters()
    {
        return $this->globalParameters;
    }

    /**
     * Verifies whether the given format is supported by this view
     *
     * @param string $format format name
     * @return bool
     */
    public function supports($format)
    {
        return isset($this->customHandlers[$format]) || method_exists($this, 'handle'.ucfirst($format));
    }

    /**
     * Handles a request with the proper handler
     *
     * Decides on which handler to use based on the request format
     *
     * @param Request $request Request object
     * @param Response $response optional response object to use
     *
     * @param Response
     */
    public function handle(Request $request = null, Response $response = null)
    {
        if (null === $request) {
            $request = $this->container->get('request');
        }

        if (null === $response) {
            $response = $this->container->get('response');
        }

        if (null === $this->format) {
            $this->setFormat($request->getRequestFormat());
        }

        $format = $this->getFormat();

        if (isset($this->customHandlers[$format])) {
            $callback = $this->customHandlers[$format];
            $response = call_user_func($callback, $this, $request, $response);
        } else {
            $response = $this->transform($request, $response, $format, $this->getTemplate());
        }

        $this->reset();
        return $response;
    }

    /**
     * Resets the state of the view object
     */
    public function reset()
    {
        $this->redirect = null;
        $this->template = null;
        $this->format = null;
        $this->renderer = 'twig';
        $this->parameters = array();
    }

    /**
     * Reset serializer service
     */
    protected function resetSerializer()
    {
        $this->serializer = null;
    }

    /**
     * Get the serializer service, add encoder in case its not yet set for the passed format
     *
     * @param string $format
     *
     * @return Symfony\Component\Serializer\SerializerInterface
     */
    protected function getSerializer($format = null)
    {
        if (null === $this->serializer) {
            $this->serializer = $this->container->get('serializer');
        }

        if (null !== $format && !$this->serializer->supports($format)) {
            $this->serializer->addEncoder($format, $this->container->get('encoder.'.$format));
        }

        return $this->serializer;
    }

    /**
     * Generic transformer
     *
     * Handles redirects, or transforms the parameters into a response content
     *
     * @param Request $request
     * @param Response $response
     * @param string $format
     * @param string $template
     *
     * @return Response
     */
    protected function transform(Request $request, Response $response, $format, $template)
    {
        if ($this->redirect) {
            $response->setRedirect($this->redirect['location'], $this->redirect['status_code']);
            return $response;
        }

        $serializer = $this->getSerializer($format);
        $encoder = $serializer->getEncoder($format);

        if ($encoder instanceof TemplatingAwareEncoderInterface) {
            $encoder->setTemplate($template);
        }

        $parameters = (array)$this->parameters;
        $content = $serializer->serialize($parameters, $format);

        $response->setContent($content);
        return $response;
    }
}
