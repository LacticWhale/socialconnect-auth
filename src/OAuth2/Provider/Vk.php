<?php
/**
 * SocialConnect project
 * @author: Patsura Dmitry https://github.com/ovr <talk@dmtry.me>
 */
declare(strict_types=1);

namespace SocialConnect\OAuth2\Provider;

use SocialConnect\Common\ArrayHydrator;
use SocialConnect\Provider\AccessTokenInterface;
use SocialConnect\Common\Entity\User;

class Vk extends \SocialConnect\OAuth2\AbstractProvider
{
    const NAME = 'vk';

    /**
     * {@inheritdoc}
     */
    protected $requestHttpMethod = 'POST';

    /**
     * {@inheritdoc}
     */
    protected $is2_1 = true;

    public function getBaseUri()
    {
        return 'https://id.vk.com/';
    }

    public function getAuthorizeUri()
    {
        return 'https://id.vk.com/authorize';
    }

    public function getRequestTokenUri()
    {
        return 'https://api.vk.com/oauth2/auth';
    }

    public function getName()
    {
        return self::NAME;
    }

    /**
     * {@inheritDoc}
     */
    public function prepareRequest(string $method, string $uri, array &$headers, array &$query, ?AccessTokenInterface $accessToken = null): void
    {
        if ($accessToken) {
            $query['access_token'] = $accessToken->getToken();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentity(AccessTokenInterface $accessToken)
    {
        $query = [
            'client_id' => $this->consumer->getKey(),
            'access_token' => $accessToken->getToken(),
        ];

        $fields = $this->getArrayOption('identity.fields', []);
        // if ($fields) {
        //     $query['field'] = implode(',', $fields);
        // }
        debugLog(__METHOD__, $query);
        try {
            $response = $this->request('GET', 'oauth2/user_info', $query, $accessToken);
        } catch (\Throwable $th) {
            debugLog($th);
            throw $th;
        }
        debugLog(__METHOD__, $response);
        $hydrator = new ArrayHydrator([
            'id' => 'id',
            'first_name' => 'firstname',
            'last_name' => 'lastname',
            'bdate' => static function ($value, User $user) {
                $user->setBirthday(
                    new \DateTime($value)
                );
            },
            'sex' => static function ($value, User $user) {
                $user->setSex($value === 1 ? User::SEX_FEMALE : User::SEX_MALE);
            },
            'screen_name' => 'username',
            'photo_max_orig' => 'pictureURL',
        ]);

        /** @var User $user */
        $user = $hydrator->hydrate(new User(), $response['response'][0]);

        // Vk returns email inside AccessToken
        $user->email = $accessToken->getEmail();
        $user->emailVerified = true;

        return $user;
    }
}
