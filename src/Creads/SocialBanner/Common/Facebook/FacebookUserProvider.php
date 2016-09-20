<?php

namespace Creads\SocialBanner\Common\Facebook;

use Symfony\Component\Security\Core\User\UserInterface;
// use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Doctrine\ORM\EntityManager;
use Creads\SocialBanner\Common\Entity\User;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Gigablah\Silex\OAuth\Security\User\Provider\OAuthUserProviderInterface;
use Gigablah\Silex\OAuth\Security\Authentication\Token\OAuthTokenInterface;

class FacebookUserProvider implements UserProviderInterface, OAuthUserProviderInterface
{
    private $em;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByUsername($username)
    {
        $user = $this->em->getRepository('Creads\SocialBanner\Common\Entity\User')->findOneByEmail($username);

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByOAuthCredentials(OAuthTokenInterface $token)
    {
        // Only handle facebook connect for now, if other OAuth providers are needed,
        // find the related account corresponding to the service
        $user = null;
        if ('facebook' === strtolower($token->getService())) {
            $user = $this->em->getRepository('Creads\SocialBanner\Common\Entity\User')->findOneByFacebookId($token->getUid());
            if (!$user) {
                $user = new User();
                $user->setFacebookId($token->getUid());

                $user->setEmail($token->getEmail());
                $user->setFullName($token->getUsername());
                $this->em->persist($user);
                $this->em->flush();
            }
        }

        return $user;
    }

    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }
        $user = $this->em->getRepository('Creads\SocialBanner\Common\Entity\User')->findOneByFacebookId($user->getFacebookId());

        return $user;
    }

    public function supportsClass($class)
    {
        return $class === 'Creads\SocialBanner\Common\Entity\User';
    }
}
