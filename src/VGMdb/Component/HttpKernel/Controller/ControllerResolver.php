<?php

namespace VGMdb\Component\HttpKernel\Controller;

use Silex\Application;
use Silex\ControllerResolver as BaseControllerResolver;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;

/**
 * Allows the controller resolver to recognize custom verbs and methods.
 *
 * @author Gigablah <gigablah@vgmdb.net>
 */
class ControllerResolver extends BaseControllerResolver
{
    protected $logger;

    /**
     * {@inheritDoc}
     */
    public function __construct(Application $app, LoggerInterface $logger = null)
    {
        $this->logger = $logger;

        parent::__construct($app, $logger);
    }

    /**
     * {@inheritDoc}
     */
    public function getController(Request $request)
    {
        if (!$controller = $request->attributes->get('_controller')) {
            if (null !== $this->logger) {
                $this->logger->warn('Unable to look for the controller as the "_controller" parameter is missing');
            }

            return false;
        }

        if (is_array($controller) || (is_object($controller) && method_exists($controller, '__invoke'))) {
            return $controller;
        }

        if (false === strpos($controller, ':')) {
            if (method_exists($controller, '__invoke')) {
                return new $controller;
            } elseif (function_exists($controller)) {
                return $controller;
            }
        }

        $callable = $this->createController($controller, $request);

        if ($callable instanceof \Closure) {
            return $callable;
        }

        list($controller, $method) = $callable;

        $verbMethod = strtolower($request->getMethod()) . ucfirst($method);
        if (method_exists($controller, $verbMethod)) {
            return array($controller, $verbMethod);
        }
        if (method_exists($controller, $method)) {
            return array($controller, $method);
        }

        throw new \InvalidArgumentException(
            sprintf('Could not resolve "%s:%s" or "%s:%s".',
                get_class($controller),
                $verbMethod,
                get_class($controller),
                $method
            )
        );
    }

    /**
     * Returns a callable for the given controller.
     *
     * @param string  $controller A Controller string
     * @param Request $request    The request object
     *
     * @return mixed A PHP callable
     */
    protected function createController($controller, $request = null)
    {
        if (false === strpos($controller, ':')) {
            $action = $request ? $request->attributes->get('_action') : '';
            $action = $action ?: 'index';
            list($class, $method) = array($controller, $action);
        } else {
            list($class, $method) = explode(':', $controller, 2);
        }

        $method .= 'Action';

        if (false !== strpos($class, '\\')) {
            $class = $class;
        } elseif (isset($this->app[$class])) {
            $class = $this->app[$class];
        } elseif (isset($this->app['namespace'])) {
            if (substr($class, -10) !== 'Controller') {
                $class .= 'Controller';
            }
            $class = $this->app['namespace'] . '\\Controllers\\' . $class;
        }

        if ($class instanceof \Closure) {
            return $class;
        }

        if (!class_exists($class)) {
            throw new \InvalidArgumentException(sprintf('Class "%s" does not exist.', $class));
        }

        $controller = new $class($this->app);

        return array($controller, $method);
    }
}
