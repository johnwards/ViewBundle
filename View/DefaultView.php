<?php

namespace Liip\ViewBundle\View;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Bundle\FrameworkBundle\Templating\TemplateReference;
use Liip\ViewBundle\Serializer\Encoder\TemplatingAwareEncoderInterface;

/**
 * DefaultView is a default view implementation
 *
 * Use it in controllers to build up a response in a format agnostic way
 * The View class takes care of encoding your data in json, xml, or renders a template for html
 * via the Serializer component.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Lukas K. Smith <smith@pooteeweet.org>
 */
class DefaultView
{
    protected $container;
    protected $serializer;

    protected $customHandlers = array();
    protected $formats;

    protected $redirect;
    protected $template;
    protected $format;
    protected $parameters;
    protected $engine;

    /**
     * Constructor
     *
     * @param ContainerInterface $container The service_container service.
     * @param array $formats The supported formats
     */
    public function __construct(ContainerInterface $container, array $formats = null)
    {
        $this->reset();
        $this->formats = (array)$formats;
        $this->container = $container;
    }

    /**
     * Resets the state of the view object
     */
    public function reset()
    {
        $this->redirect = null;
        $this->template = null;
        $this->format = null;
        $this->engine = 'twig';
        $this->parameters = array();
    }

    /**
     * Reset serializer service
     */
    public function resetSerializer()
    {
        $this->serializer = null;
    }

    /**
     * Sets what formats are supported
     *
     * @param array $formats list of supported formats
     */
    public function setFormats($formats)
    {
        $this->formats = array_replace($this->formats, $formats);
    }

    /**
     * Verifies whether the given format is supported by this view
     *
     * @param string $format format name
     * @return bool
     */
    public function supports($format)
    {
        return isset($this->customHandlers[$format]) || !empty($this->formats[$format]);
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

    /**
     * Gets a redirect
     *
     * @return array redirect location and status code
     */
    public function getRedirect()
    {
        return $this->redirect;
    }

    /**
     * Sets encoding parameters
     *
     * @param string|array $parameters parameters to be used in the encoding
     */
    public function setParameters($parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * Gets encoding parameters
     *
     * @return string|array parameters to be used in the encoding
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Sets template to use for the encoding
     *
     * @param string|array|TemplateReference $template template to be used in the encoding
     */
    public function setTemplate($template)
    {
        if (is_array($template)) {
            if (empty($template['name'])) {
                throw new \InvalidArgumentException('The "name" key must be set: '.serialize($template));
            }

            $bundle = empty($template['bundle']) ? null : $template['bundle'];
            $controller = empty($template['controller']) ? null : $template['controller'];
            $format = empty($template['format']) ? null : $template['format'];
            $engine = empty($template['engine']) ? null : $template['engine'];

            $template = new TemplateReference($bundle, $controller, $template['name'], $format, $engine);
        }

        $this->template = $template;
    }

    /**
     * Gets template to use for the encoding
     *
     * When the template is an array this method
     * ensures that the format and engine are set
     *
     * @return string|TemplateReference template to be used in the encoding
     */
    public function getTemplate()
    {
        $template = $this->template;

        if ($template instanceOf TemplateReference) {
            if (null === $template->get('format')) {
                $template->set('format', $this->getFormat());
            }

            if (null === $template->get('engine')) {
                $template->set('engine', $this->getEngine());
            }
        }

        return $template;
    }

    /**
     * Sets engine to use for the encoding
     *
     * @param string $engine engine to be used in the encoding
     */
    public function setEngine($engine)
    {
        $this->engine = $engine;
    }

    /**
     * Gets engine to use for the encoding
     *
     * @return string engine to be used in the encoding
     */
    public function getEngine()
    {
        return $this->engine;
    }

    /**
     * Sets encoding format
     *
     * @param string $format format to be used in the encoding
     */
    public function setFormat($format)
    {
        $this->format = $format;
    }

    /**
     * Gets encoding format
     *
     * @return string format to be used in the encoding
     */
    public function getFormat()
    {
        return $this->format;
    }

    /**
     * Set the serializer service
     *
     * @param Symfony\Component\Serializer\SerializerInterface $serializer a serializer instance
     */
    public function setSerializer($serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * Get the serializer service, add encoder in case there is none set for the given format
     *
     * @param string $format
     *
     * @return Symfony\Component\Serializer\SerializerInterface
     */
    public function getSerializer($format = null)
    {
        if (null === $this->serializer) {
            $this->serializer = $this->container->get('liip_view.serializer');
        }

        if (null !== $format && !$this->serializer->hasEncoder($format)) {
            $this->serializer->setEncoder($format, $this->container->get($this->formats[$format]));
        }

        return $this->serializer;
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
            if (!$this->supports($format)) {
                throw new NotFoundHttpException("Format '$format' not supported, handler must be implemented");
            }
            $response = $this->transform($request, $response, $format, $this->getTemplate());
        }

        $this->reset();

        return $response;
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

        $content = $serializer->serialize($this->getParameters(), $format);

        $response->setContent($content);
        return $response;
    }
}
