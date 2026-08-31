<?php
/**
 * api/checkout.php
 * ---------------------------------------------------------------------------
 * Confirma la compra: recibe el carrito, lo revalida contra la base y graba
 * el pedido dentro de una transacción.
 *
 * POST /api/checkout.php   body JSON:
 * {
 *   "csrf": "...",
 *   "cliente": {"nombre":"...","email":"...","telefono":"...","direccion":"..."},
 *   "items":   [{"producto_id":1,"cantidad":2}, ...]
 * }
 *
 * IMPORTANTE: el body NO incluye precios. El servidor los toma de la BD.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/modelo_pedidos.php';

exigir_metodo('POST');

$in = body_json();
csrf_validar($in['csrf'] ?? null);

$items = $in['items'] ?? null;
if (!is_array($items) || $items === []) {
    json_error('El carrito está vacío.', 422);
}
if (count($items) > 50) {
    json_error('Demasiados productos distintos en el carrito.', 422);
}

$usuario = usuario_actual();

// Si hay sesión, los datos del usuario logueado tienen prioridad:
// así nadie puede hacer un pedido "a nombre de otro" desde el front.
$clienteEntrada = is_array($in['cliente'] ?? null) ? $in['cliente'] : [];

$cliente = [
    'nombre'    => $usuario['nombre'] ?? (string) ($clienteEntrada['nombre'] ?? ''),
    'email'     => $usuario['email']  ?? (string) ($clienteEntrada['email'] ?? ''),
    'telefono'  => (string) ($clienteEntrada['telefono'] ?? ($usuario['telefono'] ?? '')),
    'direccion' => (string) ($clienteEntrada['direccion'] ?? ($usuario['direccion'] ?? '')),
];

try {
    $r = PedidoRepo::crear($items, $cliente, isset($usuario['id']) ? (int) $usuario['id'] : null);

    if (!$r['ok']) {
        json_error($r['error'], 409);   // 409 Conflict: stock / datos inconsistentes
    }

    json_ok([
        'mensaje' => 'Pedido registrado correctamente.',
        'pedido'  => $r['pedido'],
    ], 201);
} catch (Throwable $e) {
    error_log('[api/checkout] ' . $e->getMessage());
    json_error(
        APP_ENV === 'dev' ? $e->getMessage() : 'No se pudo procesar el pedido.',
        500
    );
}
