<?php

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\ValidationData;
use Lcobucci\JWT\Signer\Hmac\Sha256;

/**
 * Auth
 * @author ROC <i@rocs.me>
 */
class Auth
{
    const APP_NAME = 'Batio';
    const APP_DEFAULT_KEY = 'BA6okldBWmX3AsGN';

    public static $callback = null;
    public static $instance = null;
    public static $authActions = [];

    public function __construct()
    {
    }

    /**
     * Get JWT Token
     *
     * @param integer $uid
     * @param string $claimToken
     * @return string
     */
    public static function getToken($uid, $claimToken = '')
    {
        return (new Builder())
                ->setIssuer(env('APP_NAME', self::APP_NAME)) // Configures the issuer (iss claim)
                ->setAudience($uid) // Configures the audience (aud claim)
                ->setId(guid($uid), true) // Configures the id (jti claim), replicating as a header item
                ->setIssuedAt(time()) // Configures the time that the token was issue (iat claim)
                ->setNotBefore(time()) // Configures the time that the token can be used (nbf claim)
                ->setExpiration(time() + env('JWT_TTL', 60) * 60) // Configures the expiration time of the token (exp claim)
                ->set('uid', $uid) // Configures a new claim, called "uid"
                ->set('claimToken', $claimToken) // Configures a new claim, called "uid"
                ->sign((new Sha256()), env('JWT_SECRET', self::APP_DEFAULT_KEY)) // creates a signature using "testing" as key
                ->getToken(); // Retrieves the generated token
    }

    /**
     * Verify Token
     * @method verify
     * @param  [type] $token [description]
     * @return [type]        [description]
     */
    public static function verify($token)
    {
        // Reset UID 0
        app()->set('uid', 0);
        app()->set('auth.error', '');

        try {
            // Parses from a string
            $token = (new Parser())->parse((string) $token);

            if ($token->verify((new Sha256()), env('JWT_SECRET', self::APP_DEFAULT_KEY))) {
                $data = new ValidationData();
                $data->setIssuer(env('APP_NAME', self::APP_NAME));

                if ($token->validate($data) && $token->getClaim('uid') > 0) {
                    app()->set('uid', $token->getClaim('uid'));
                    app()->set('claimToken', $token->getClaim('claimToken'));

                    return 1;
                }

                $exp = $token->getClaim('exp');
                if ($exp >= time() + env('JWT_REFRESH_TTL', 60) * 60) {
                    // Need refresh token
                    app()->set('auth.error', 'Token has expired.');
                    return 0;
                }
            }

            // Unauthentication
            app()->set('auth.error', 'Invalid Token.');
        } catch (Exception $e) {
            // Invaild Token
            app()->set('auth.error', $e->getMessage());
        }

        return -1;
    }
}
