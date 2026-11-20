// file: api/config/jwt_config.php
<?php

namespace MIMA\Config;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

class JWTConfig
{
    private static $secret;
    private static $expiry;

    public static function init()
    {
        $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();
        
        self::$secret = $_ENV['JWT_SECRET'] ?? 'default-secret-key-change-me';
        self::$expiry = (int)($_ENV['JWT_EXPIRY'] ?? 86400);
    }

    public static function encode($payload)
    {
        self::init();
        $payload['exp'] = time() + self::$expiry;
        return JWT::encode($payload, self::$secret, 'HS256');
    }

    public static function decode($token)
    {
        self::init();
        try {
            return (array)JWT::decode($token, new Key(self::$secret, 'HS256'));
        } catch (ExpiredException $e) {
            return ['error' => 'Token expired'];
        } catch (SignatureInvalidException $e) {
            return ['error' => 'Invalid token signature'];
        } catch (\Exception $e) {
            return ['error' => 'Invalid token'];
        }
    }

    public static function getSecret()
    {
        self::init();
        return self::$secret;
    }
}