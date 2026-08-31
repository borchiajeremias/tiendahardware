<?php
/**
 * config/config.php
 * ---------------------------------------------------------------------------
 * Configuración central del proyecto. Es el ÚNICO archivo que deberías editar
 * al mover el sitio a otro servidor.
 *
 * En producción: dejá este archivo fuera del document root o protegelo,
 * y mové las credenciales a variables de entorno.
 */

declare(strict_types=1);

// --- Entorno ----------------------------------------------------------------
// 'dev'  -> muestra errores en pantalla (solo para desarrollo local)
// 'prod' -> oculta errores al usuario y los escribe en el log del servidor
define('APP_ENV', 'dev');

// --- Base de datos ----------------------------------------------------------
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'hardwarestore');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// --- Aplicación -------------------------------------------------------------
define('APP_NAME', 'HardStore');
define('APP_URL', '');            // subcarpeta, ej: '/hardwarestore'. Vacío si está en la raíz.
define('MONEDA', 'ARS');
define('IVA', 0.21);              // 21 % — se muestra desglosado en el checkout

// --- Reporte de errores según entorno --------------------------------------
if (APP_ENV === 'dev') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

date_default_timezone_set('America/Argentina/Buenos_Aires');
