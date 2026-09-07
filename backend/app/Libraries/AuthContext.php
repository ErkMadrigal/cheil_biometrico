<?php

namespace App\Libraries;

/**
 * Contenedor simple en memoria para el usuario autenticado del request actual.
 * Lo llena JwtAuthFilter::before() y lo leen los controladores.
 */
class AuthContext
{
    protected static ?array $user = null;

    public static function setUser(array $claims): void
    {
        static::$user = $claims;
    }

    public static function user(): ?array
    {
        return static::$user;
    }

    public static function id(): ?int
    {
        return isset(static::$user['sub']) ? (int) static::$user['sub'] : null;
    }

    public static function role(): ?string
    {
        return static::$user['role'] ?? null;
    }

    public static function employeeId(): ?int
    {
        return isset(static::$user['employee_id']) && static::$user['employee_id'] !== null
            ? (int) static::$user['employee_id']
            : null;
    }
}
