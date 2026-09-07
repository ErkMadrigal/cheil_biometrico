<?php

namespace App\Libraries;

use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use UnexpectedValueException;

/**
 * Envoltura simple sobre firebase/php-jwt para emitir y validar
 * los tokens que usan tanto el web panel (Vue) como la app movil (Ionic).
 */
class Jwt
{
    protected string $secret;
    protected string $algo = 'HS256';
    protected int $ttl;

    public function __construct()
    {
        $this->secret = (string) env('JWT_SECRET', 'change-me-in-env');
        $this->ttl    = (int) env('JWT_TTL_SECONDS', 43200); // 12h por defecto
    }

    public function issue(array $claims): string
    {
        $now = time();
        $payload = array_merge($claims, [
            'iat' => $now,
            'exp' => $now + $this->ttl,
        ]);

        return FirebaseJWT::encode($payload, $this->secret, $this->algo);
    }

    /**
     * @return array|null null si el token es invalido o expiro
     */
    public function verify(string $token): ?array
    {
        try {
            $decoded = FirebaseJWT::decode($token, new Key($this->secret, $this->algo));
            return (array) $decoded;
        } catch (ExpiredException|UnexpectedValueException $e) {
            return null;
        }
    }
}
