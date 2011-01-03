<?php

namespace Bundle\Liip\ViewBundle\View;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Empty view implementation that just returns a response with predefined
 * status codes and headers
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class EmptyView extends AbstractView
{
    protected $statusCode;
    protected $location;

    /**
     * Constructor
     *
     * Takes an optional array of global parameters, those will be merged with the
     * normal parameters before rendering an html template, which makes them useful for
     * data that you need to have accessible in the layout for every page on your site.
     *
     * @param int $statusCode http status code for this view
     */
    public function __construct($statusCode)
    {
        $this->statusCode = $statusCode;
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
    public function handle(Request $request, Response $response = null)
    {
        if (null === $request) {
            $request = $this->container->get('request');
        }
        if (null === $response) {
            $response = $this->container->get('response');
        }
        $format = $request->getRequestFormat();

        if (isset($this->handlers[$format])) {
            $callback = $this->customHandlers[$format];
            $response = call_user_func($callback, $this, $request, $response);
        } else {
            $response->setStatusCode($this->statusCode);
            if (in_array($this->statusCode, array(300, 301, 302, 303, 305, 307))) {
                if (!$this->location) {
                    throw new \InvalidArgumentException('Location missing for response with status code '.$this->statusCode);
                }
                $response->headers->set('Location', $this->location);
            }
        }

        return $response;
    }

    /**
     * Sets a redirect using a route and parameters
     *
     * @param string $route route name
     * @param array $parameters route parameters
     * @param int $code optional http status code
     * @return EmptyView chainable object
     */
    public function setRouteRedirect($route, $parameters = array(), $code = 302)
    {
        $this->statusCode = $code;
        $this->location = $this->container->get('router')->generate($route, $parameters);
        return $this;
    }

    /**
     * Sets a redirect using an URI
     *
     * @param string $uri URI
     * @param int $code optional http status code
     * @return EmptyView chainable object
     */
    public function setUriRedirect($uri, $code = 302)
    {
        $this->statusCode = $code;
        $this->location = $uri;
        return $this;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function setStatusCode($code)
    {
        $this->statusCode = $code;
        return $this;
    }

    public function getLocation()
    {
        return $this->location;
    }

    public function setLocation($location)
    {
        $this->location = $location;
        return $this;
    }
}
