# passport-wechat-oauth-grant
增加一个可以通过微信Oauth授权code登录passport的Grant

## 安装
通过Composer安装
```sh
$ composer require fmwww/passport-wechat-oauth-grant
```

如果你的Laravel版本 < 5.5, 你需要在 `config/app.php` 的providers数组中增加:
```php
Fmwww\PassportWechatOauthGrant\WechatOauthGrantServiceProvider::class,
```

## 配置
使用之前需要先配置好微信的 `app_id` 和 `app_secret`
使用下面的命令拷贝配置文件到 `config` :

```sh
$ php artisan vendor:publish --provider="Fmwww\PassportWechatOauthGrant\WechatOauthGrantServiceProvider"
```

```php
return [
    /*
     * 微信app_id
     */
    'app_id' => env('WECHAT_OAUTH_GRANT_APP_ID', ''),
    /*
     * 微信app_secret
     */
    'app_secret' => env('WECHAT_OAUTH_GRANT_APP_SECRET', ''),
];
```
> 推荐使用 `.env` 文件进行配置


## 用法
- 使用**POST**方法去请求`https://your-site.com/oauth/token`
- **POST**请求体里的需要将**grant_type**设置为**wechat_oauth**，同时将**code**设置为**微信返回的授权code**
- 系统将会根据 `config/auth.php`里面的 `api guard` 设置的用户模型去寻找用户，如果用户模型定义了 `findForWechatOauth` 方法，那么就会使用这个方法返回的用户进行认证，否则就会根据`openid`字段去寻找用户进行认证。
- 如果用户存在，就会成功返回 `access_token` 和 `refresh_token`。
### 例子
#### 请求方法
```php
$http = new GuzzleHttp\Client;

$response = $http->post('http://your-app.com/oauth/token', [
    'form_params' => [
        'grant_type' => 'wechat_oauth',
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'code' => '001qJKS42a3r3N0wrDU42IHrS42qJKSN', #微信的授权code
        'scope' => '',
    ],
]);

return json_decode((string) $response->getBody(), true);
```
#### 自定义 `findForWechatOauth`方法
```php
public function findForWechatOauth($tokens)
    {
        // 获取openid
        $openid = $tokens->openid;
        
        // 获取access_token
        $access_token = $tokens->access_token;
        
        // 获取expires_in
        $expires_in = $tokens->expires_in;
        
        // 获取refresh_token
        $refresh_token = $tokens->refresh_token;
        
        // 获取scope
        $scope = $tokens->scope;

        // 通过openid查找，如果这个方法没有定义，默认就是这样查找
        $user = $this->where('openid', $openid)->first();
        
        // 你也可以自己创建用户然后返回
        if (empty($user)) {
            $this->openid = $openid;
            $this->save();
        }
        // 返回用户
        return $user ?: $this;
    }
```
