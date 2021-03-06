<?php

namespace VGMdb\Component\Opauth\Security\Http\Firewall;

use VGMdb\Component\Opauth\Security\Core\Authentication\Token\OpauthToken;
use VGMdb\Component\User\Util\UserManipulator;
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Firewall\AbstractAuthenticationListener;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * Authentication listener handling OAuth Authentication responses.
 *
 * @author Gigablah <gigablah@vgmdb.net>
 */
class OpauthAuthenticationListener extends AbstractAuthenticationListener
{
    private $oauthProvider;
    private $csrfProvider;
    private $trustResolver;
    private $userManipulator;
    private $token;
    protected $httpUtils;

    /**
     * {@inheritdoc}
     */
    public function __construct(SecurityContextInterface $securityContext, AuthenticationManagerInterface $authenticationManager, SessionAuthenticationStrategyInterface $sessionStrategy, HttpUtils $httpUtils, $providerKey, \Opauth $oauthProvider, AuthenticationTrustResolverInterface $trustResolver, UserManipulator $userManipulator, AuthenticationSuccessHandlerInterface $successHandler = null, AuthenticationFailureHandlerInterface $failureHandler = null, array $options = array(), LoggerInterface $logger = null, EventDispatcherInterface $dispatcher = null, CsrfProviderInterface $csrfProvider = null)
    {
        parent::__construct($securityContext, $authenticationManager, $sessionStrategy, $httpUtils, $providerKey, $successHandler, $failureHandler, array_merge(array(
            'csrf_parameter' => '_csrf_token',
            'intention'      => 'oauth',
            'post_only'      => false,
        ), $options), $logger, $dispatcher);
        $this->oauthProvider   = $oauthProvider;
        $this->csrfProvider    = $csrfProvider;
        $this->trustResolver   = $trustResolver;
        $this->userManipulator = $userManipulator;
        $this->token           = $securityContext->getToken();
        $this->httpUtils       = $httpUtils;
    }

    /**
     * {@inheritDoc}
     */
    protected function requiresAuthentication(Request $request)
    {
        if ($this->httpUtils->checkRequestPath($request, $this->options['login_path'])) {
            if ($this->options['post_only'] && !$request->isMethod('post')) {
                return false;
            }
            return true;
        }

        return parent::requiresAuthentication($request);
    }

    /**
     * {@inheritDoc}
     */
    protected function attemptAuthentication(Request $request)
    {
        $opauth = $this->oauthProvider;

        // redirect to auth provider
        if ($this->httpUtils->checkRequestPath($request, $this->options['login_path'])) {
            if ($this->options['post_only'] && !$request->isMethod('post')) {
                if (null !== $this->logger) {
                    $this->logger->debug(sprintf('Authentication method not supported: %s.', $request->getMethod()));
                }

                return null;
            }
            // CSRF checking only upon login
            if (null !== $this->csrfProvider) {
                $csrfToken = $request->get($this->options['csrf_parameter'], null, true);

                if (false === $this->csrfProvider->isCsrfTokenValid($this->options['intention'], $csrfToken)) {
                    throw new InvalidCsrfTokenException('Invalid CSRF token.');
                }
            }

            return $opauth->run();
        }

        $response = null;
        switch($opauth->env['callback_transport']) {
            case 'session':
                $response = $_SESSION['opauth'];
                unset($_SESSION['opauth']);
                break;
            case 'post':
            case 'get':
                $response = unserialize(base64_decode($request->request->get('opauth')));
                break;
            default:
                throw new \LogicException(
                    sprintf('The "%s" callback transport is not supported.', $opauth->env['callback_transport'])
                );
        }

        if (is_null($response) || !is_array($response)) {
            throw new AuthenticationException('Authentication response is invalid.');
        }
        if (array_key_exists('error', $response)) {
            throw new AuthenticationException(sprintf('Authentication provider error: %s', $response['error']['code']));
        }
        if (!isset($response['auth']) || !isset($response['auth']['provider']) || !isset($response['auth']['uid']) || !isset($response['auth']['info'])) {
            throw new AuthenticationException('Authentication data missing.');
        }
        if (!$opauth->validate(sha1(print_r($response['auth'], true)), $response['timestamp'], $response['signature'], $reason)) {
            throw new AuthenticationException(sprintf('Authentication signature invalid: %s', $reason));
        }

        $username = '';
        if (isset($response['auth']['info']['nickname'])) {
            $username = $response['auth']['info']['nickname'];
        }
        if (!$username && isset($response['auth']['info']['name'])) {
            $username = $response['auth']['info']['name'];
        }
        if (!$username) {
            $username = $response['auth']['uid'];
        }

        $email = '';
        if (isset($response['auth']['info']['email'])) {
            $email = $response['auth']['info']['email'];
        }
        if (!$email) {
            $email = str_replace(' ', '', $username) . '@' . strtolower($response['auth']['provider']) . '.com';
        }

        $authToken = new OpauthToken($this->providerKey);
        $authToken->setUser($username);
        $authToken->provider = $response['auth']['provider'];
        $authToken->providerId = $response['auth']['uid'];

        try {
            return $this->authenticationManager->authenticate($authToken);
        } catch (BadCredentialsException $e) {
            // if user is already logged in, add auth provider, otherwise create new account
            if ($this->token && !$this->trustResolver->isAnonymous($token)) {
                $user = $this->token->getUser();
            } else {
                $user = $this->userManipulator->createOrFindByEmail($username, $email);
            }

            $this->userManipulator->addAuthProvider($user, $authToken->provider, $authToken->providerId);

            return $this->authenticationManager->authenticate($authToken);
        }
    }
}
