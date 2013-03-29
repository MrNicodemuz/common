<?php

namespace VGMdb\Component\HttpFoundation;

use VGMdb\Component\HttpFoundation\Util\AcceptNegotiator;
use VGMdb\Component\HttpFoundation\EventListener\SubdomainListener;
use VGMdb\Component\HttpFoundation\EventListener\ExtensionListener;
use VGMdb\Component\HttpFoundation\EventListener\RequestFormatListener;
use Silex\Application;
use Silex\ServiceProviderInterface;

/**
 * Handles subdomain, extension, accept header format and version negotiation.
 *
 * @author Gigablah <gigablah@vgmdb.net>
 */
class FormatNegotiatorProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['request.format.negotiator'] = $app->share(function ($app) {
            return new AcceptNegotiator();
        });

        $app['request.format.extensions'] = array('json', 'xml', 'gif', 'qrcode');

        $app['request.format.default_version'] = '1.0';

        Request::addFormat('gif', array('image/gif'));
        Request::addFormat('qrcode', array('image/png'));
    }

    public function boot(Application $app)
    {
        $app['dispatcher']->addSubscriber(new SubdomainListener($app));     // 512
        $app['dispatcher']->addSubscriber(new ExtensionListener($app));     // 256
        $app['dispatcher']->addSubscriber(new RequestFormatListener($app, $app['request_context'])); // 128, -5, -16
    }
}