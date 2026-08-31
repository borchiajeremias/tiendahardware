<?php
/**
 * includes/funciones.php
 * ---------------------------------------------------------------------------
 * Helpers transversales: escape de salida, respuestas JSON, sesiones, CSRF.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

/* -------------------------------------------------------------------------
 *  SALIDA SEGURA
 * ---------------------------------------------------------------------- */

/**
 * Escapa texto para insertarlo en HTML (previene XSS).
 * Regla del proyecto: TODO dato que venga de la BD o del usuario pasa por acá.
 */
function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Formatea un número como precio en pesos: 1234.5 -> "$ 1.234,50" */
function precio(float|string $monto): string
{
    return '$ ' . number_format((float) $monto, 2, ',', '.');
}

/* -------------------------------------------------------------------------
 *  RESPUESTAS JSON (para los endpoints de /api)
 * ---------------------------------------------------------------------- */

/** Envía una respuesta JSON y corta la ejecución. */
function json_respuesta(array $datos, int $codigo = 200): never
{
    if (!headers_sent()) {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
    }
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Atajo para respuestas exitosas. */
function json_ok(array $datos = [], int $codigo = 200): never
{
    json_respuesta(['ok' => true] + $datos, $codigo);
}

/** Atajo para respuestas de error. */
function json_error(string $mensaje, int $codigo = 400, array $extra = []): never
{
    json_respuesta(['ok' => false, 'error' => $mensaje] + $extra, $codigo);
}

/** Rechaza el request si el método HTTP no es el esperado. */
function exigir_metodo(string $metodo): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== strtoupper($metodo)) {
        json_error('Método no permitido.', 405);
    }
}

/**
 * Lee y decodifica el body JSON de un request.
 * Devuelve array vacío si el body no es JSON válido.
 */
function body_json(): array
{
    $crudo = file_get_contents('php://input') ?: '';
    $datos = json_decode($crudo, true);
    return is_array($datos) ? $datos : [];
}

/* -------------------------------------------------------------------------
 *  SESIONES Y AUTENTICACIÓN
 * ---------------------------------------------------------------------- */

/** Inicia la sesión con cookies endurecidas (una sola vez por request). */
function sesion_iniciar(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,                                  // inaccesible desde JS
        'samesite' => 'Lax',                                 // mitiga CSRF
        'secure'   => !empty($_SERVER['HTTPS']),             // solo HTTPS si está disponible
    ]);
    session_name('HSSESSID');
    session_start();
}

/** Devuelve el usuario logueado (array) o null. */
function usuario_actual(): ?array
{
    sesion_iniciar();
    return $_SESSION['usuario'] ?? null;
}

/** ¿Hay un usuario autenticado? */
function esta_logueado(): bool
{
    return usuario_actual() !== null;
}

/** Corta el request con 401 si no hay sesión (para endpoints protegidos). */
function exigir_login(): array
{
    $u = usuario_actual();
    if ($u === null) {
        json_error('Necesitás iniciar sesión para continuar.', 401);
    }
    return $u;
}

/* -------------------------------------------------------------------------
 *  CSRF
 *  Token de un solo valor por sesión, verificado en toda petición que
 *  modifique estado (registro, login, checkout).
 * ---------------------------------------------------------------------- */

function csrf_token(): string
{
    sesion_iniciar();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Valida el token recibido; corta con 419 si no coincide. */
function csrf_validar(?string $token): void
{
    sesion_iniciar();
    $esperado = $_SESSION['csrf'] ?? '';
    if ($esperado === '' || !is_string($token) || !hash_equals($esperado, $token)) {
        json_error('Token de seguridad inválido. Recargá la página.', 419);
    }
}
