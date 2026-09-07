<?php

namespace Config;

use App\Filters\CorsFilter;
use App\Filters\JwtAuthFilter;
use CodeIgniter\Config\BaseConfig;

/**
 * NOTA DE INSTALACION:
 * Este archivo reemplaza al Config/Filters.php que genera "composer create-project".
 * Si quieres conservar los filtros por defecto de CI4 (csrf, toolbar, honeypot, etc),
 * copia sus alias desde el archivo original y agregalos aqui junto a los nuestros
 * (jwtAuth, cors). Ver README.md del backend para el paso a paso de instalacion.
 */
class Filters extends BaseConfig
{
    /**
     * @var array<string, class-string>
     */
    public array $aliases = [
        'jwtAuth' => JwtAuthFilter::class,
        'cors'    => CorsFilter::class,
    ];

    /**
     * Filtros que corren en TODAS las rutas.
     */
    public array $globals = [
        'before' => [],
        'after'  => [],
    ];

    /**
     * Filtros aplicados solo a ciertos metodos HTTP.
     */
    public array $methods = [];

    /**
     * Filtros aplicados a rutas especificas (alternativa a ponerlos en Routes.php).
     * Aqui los dejamos vacios porque ya se aplican por grupo directamente en Routes.php.
     */
    public array $filters = [];
}
