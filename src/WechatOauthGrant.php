<?php
/**
 * Created by PhpStorm.
 * User: fmw
 * Date: 2018/3/25
 * Time: 下午12:26
 */

namespace Fmwww\PassportWechatOauthGrant;


use DateInterval;
use GuzzleHttp\Client;
use Laravel\Passport\Bridge\RefreshTokenRepository;
use Laravel\Passport\Bridge\User;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AbstractGrant;
use League\OAuth2\Server\RequestEvent;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

class WechatOauthGrant extends AbstractGrant
{
    public function __construct(RefreshTokenRepository $refreshTokenRepository)
    {
        $this->setRefreshTokenRepository($refreshTokenRepository);

        $this->refreshTokenTTL = new DateInterval('P1M');
    }

    /**
     * {@inheritdoc}
     */
    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        DateInterval $accessTokenTTL
    )
    {
        // Validate request
        $client = $this->validateClient($request);
        $scopes = $this->validateScopes($this->getRequestParameter('scope', $request, $this->defaultScope));

        $user = $this->validateUser($request, $client);

        // Finalize the requested scopes
        $finalizedScopes = $this->scopeRepository->finalizeScopes($scopes, $this->getIdentifier(), $client, $user->getIdentifier());

        // Issue and persist new tokens
        $accessToken = $this->issueAccessToken($accessTokenTTL, $client, $user->getIdentifier(), $finalizedScopes);
        $refreshToken = $this->issueRefreshToken($accessToken);

        // Inject tokens into response
        $responseType->setAccessToken($accessToken);
        $responseType->setRefreshToken($refreshToken);

        return $responseType;
    }

    public function getIdentifier()
    {
        return 'wechat_oauth';
    }

    /**
     * @param ServerRequestInterface $request
     * @param ClientEntityInterface $client
     *
     * @throws OAuthServerException
     *
     * @return UserEntityInterface
     * @throws \Exception
     */
    protected function validateUser(ServerRequestInterface $request, ClientEntityInterface $client)
    {
        $code = $this->getRequestParameter('code', $request);

        if (is_null($code)) {
            throw OAuthServerException::invalidRequest('code');
        }

        $user = $this->getUserEntityByWechatCode($code, $this->getIdentifier(), $client);

        if ($user instanceof UserEntityInterface === false) {
            $this->getEmitter()->emit(new RequestEvent(RequestEvent::USER_AUTHENTICATION_FAILED, $request));

            throw OAuthServerException::invalidCredentials();
        }

        return $user;
    }

    /**
     * @param $code
     * @param $grantType
     * @param ClientEntityInterface $clientEntity
     * @return User
     * @throws \Exception
     */
    protected function getUserEntityByWechatCode($code, $grantType, ClientEntityInterface $clientEntity)
    {
        $provider = config('auth.guards.api.provider');

        if (is_null($model = config('auth.providers.' . $provider . '.model'))) {
            throw new RuntimeException('Unable to determine authentication model from configuration.');
        }

        $app_id = config('wechat-oauth-grant.app_id');
        $app_secret = config('wechat-oauth-grant.app_secret');
        if (empty($app_id) || empty($app_secret)) {
            throw new RuntimeException('Wechat app_id or app_secret in configuration is undefined.');
        }

        $tokens = $this->getOauthAccessToken($code, $app_id, $app_secret);

        if (method_exists($model, 'findForWechatOauth')) {
            $user = (new $model)->findForWechatOauth($tokens);
        } else {
            $user = (new $model)->where('openid', $tokens->openid)->first();
        }

        if (!$user) {
            return;
        }

        return new User($user->getAuthIdentifier());
    }

    /**
     * @param $code
     * @return mixed
     * @throws \Exception
     */
    public function getOauthAccessToken($code, $app_id, $app_secret)
    {
        $response = (new Client())->get('https://api.weixin.qq.com/sns/oauth2/access_token?appid=' . $app_id . '&secret=' . $app_secret . '&code=' . $code . '&grant_type=authorization_code');

        if ($response->getStatusCode() != 200) {
            throw new OAuthServerException('Request wechat access_token failed.', 100, 'request_fail', 500);
        }

        $response_json = json_decode($response->getBody());

        if (isset($response_json->errcode)) {
            throw new OAuthServerException('Request wechat access_token failed.', 101, 'wechat_error', 500, 'errcode:' . $response_json->errcode . ',errmsg:' . $response_json->errmsg);
        }

        return $response_json;
    }
}