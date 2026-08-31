<?php
/**
 * api/auth.php
 * ---------------------------------------------------------------------------
 * Endpoint único de autenticación. Todas las acciones son POST + JSON + CSRF.
 *
 * POST /api/auth.php   body JSON:
 *   {"accion":"registro","nombre":"...","email":"...","password":"...",
 *    "telefono":"...","direccion":"...","csrf":"..."}
 *   {"accion":"login","email":"...","password":"...","csrf":"..."}
 *   {"accion":"logout","csrf":"..."}
 *   {"accion":"sesion"}            -> consulta el estado actual (no requiere CSRF)
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/modelo_usuarios.php';
require_once __DIR__ . '/../includes/modelo_pedidos.php';

exigir_metodo('POST');

$in     = body_json();
$accion = (string) ($in['accion'] ?? '');

try {
    switch ($accion) {

        // --- Estado de la sesión (lo usa app.js al cargar la página) ----
        case 'sesion':
            $u = usuario_actual();
            json_ok([
                'autenticado' => $u !== null,
                'usuario'     => $u,
                'csrf'        => csrf_token(),
            ]);
            // no break: json_ok() termina el script

        // --- Registro ---------------------------------------------------
        case 'registro':
            csrf_validar($in['csrf'] ?? null);

            $r = UsuarioRepo::registrar(
                (string) ($in['nombre'] ?? ''),
                (string) ($in['email'] ?? ''),
                (string) ($in['password'] ?? ''),
                isset($in['telefono']) ? (string) $in['telefono'] : null,
                isset($in['direccion']) ? (string) $in['direccion'] : null
            );

            if (!$r['ok']) {
                json_error($r['error'], 422);
            }

            // Registro exitoso -> login automático, mejor experiencia.
            $login = UsuarioRepo::login((string) $in['email'], (string) $in['password']);
            json_ok([
                'mensaje' => 'Cuenta creada. ¡Bienvenido!',
                'usuario' => $login['usuario'] ?? $r['usuario'],
                'csrf'    => csrf_token(),
            ], 201);

        // --- Login ------------------------------------------------------
        case 'login':
            csrf_validar($in['csrf'] ?? null);

            $r = UsuarioRepo::login(
                (string) ($in['email'] ?? ''),
                (string) ($in['password'] ?? '')
            );

            if (!$r['ok']) {
                // 401 y mensaje genérico: no revelamos qué campo falló.
                json_error($r['error'], 401);
            }

            json_ok([
                'mensaje' => 'Sesión iniciada.',
                'usuario' => $r['usuario'],
                'csrf'    => csrf_token(),   // el id de sesión cambió: token nuevo
            ]);

        // --- Logout -----------------------------------------------------
        case 'logout':
            csrf_validar($in['csrf'] ?? null);
            UsuarioRepo::logout();
            json_ok(['mensaje' => 'Sesión cerrada.', 'csrf' => csrf_token()]);

        // --- Mis pedidos (requiere sesión) ------------------------------
        case 'mis_pedidos':
            $u = exigir_login();
            json_ok(['pedidos' => PedidoRepo::porUsuario((int) $u['id'])]);

        default:
            json_error('Acción no reconocida.', 400);
    }
} catch (Throwable $e) {
    error_log('[api/auth] ' . $e->getMessage());
    json_error(
        APP_ENV === 'dev' ? $e->getMessage() : 'Error interno.',
        500
    );
}
