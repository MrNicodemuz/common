<?php

namespace VGMdb\Component\OAuth\Security\Core\Authentication\Token;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

/**
 * Token for OAuth Authentication responses.
 *
 * @author Gigablah <gigablah@vgmdb.net>
 */
class OAuthToken extends AbstractToken
{
    public $provider;
    public $providerId;
    private $providerKey;

    public function __construct($providerKey, array $roles = array())
    {
        parent::__construct($roles);

        if (empty($providerKey)) {
            throw new \InvalidArgumentException('$providerKey must not be empty.');
        }

        $this->providerKey = $providerKey;

        if ($roles) {
            $this->setAuthenticated(true);
        }
    }

    public function getCredentials()
    {
        return '';
    }

    public function getProviderKey()
    {
        return $this->providerKey;
    }

    public function serialize()
    {
        return serialize(array($this->provider, $this->providerId, $this->providerKey, parent::serialize()));
    }

    public function unserialize($str)
    {
        list($this->provider, $this->providerId, $this->providerKey, $parentStr) = unserialize($str);
        parent::unserialize($parentStr);
    }
}
