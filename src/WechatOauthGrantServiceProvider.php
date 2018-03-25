<?php

namespace Fmwww\PassportWechatOauthGrant;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Bridge\RefreshTokenRepository;
use Laravel\Passport\Passport;
use League\OAuth2\Server\AuthorizationServer;

class WechatOauthGrantServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/wechat-oauth-grant.php' => config_path('wechat-oauth-grant.php'),
        ]);

        app(AuthorizationServer::class)->enableGrantType($this->makeWechatOauthGrant(), Passport::tokensExpireIn());
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    protected function makeWechatOauthGrant()
    {
        $grant = new WechatOauthGrant(
            $this->app->make(RefreshTokenRepository::class)
        );
        $grant->setRefreshTokenTTL(Passport::refreshTokensExpireIn());
        return $grant;
    }
}
