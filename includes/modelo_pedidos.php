<?php
/**
 * includes/modelo_pedidos.php
 * ---------------------------------------------------------------------------
 * Creación de pedidos.
 *
 * Punto clave de arquitectura: los PRECIOS NUNCA se toman del carrito del
 * navegador. El cliente sólo manda {producto_id, cantidad}; el servidor
 * relee el precio y el stock desde la base. Confiar en el precio que envía
 * el front es el bug de seguridad clásico de todo e-commerce casero.
 *
 * Todo el alta corre dentro de una transacción: o se graba el pedido completo
 * con su detalle y el descuento de stock, o no se graba nada.
 */

declare(strict_types=1);

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/modelo_productos.php';

final class PedidoRepo
{
    /**
     * @param array<int,array{producto_id:int,cantidad:int}> $items
     * @param array{nombre:string,email:string,telefono?:?string,direccion?:?string} $cliente
     * @return array{ok:bool, error?:string, pedido?:array}
     */
    public static function crear(array $items, array $cliente, ?int $usuarioId = null): array
    {
        // --- Normalizar y consolidar el carrito ----------------------------
        $solicitado = [];
        foreach ($items as $it) {
            $pid = (int) ($it['producto_id'] ?? 0);
            $cant = (int) ($it['cantidad'] ?? 0);
            if ($pid <= 0 || $cant <= 0) {
                continue;
            }
            if ($cant > 99) {
                return ['ok' => false, 'error' => 'La cantidad máxima por producto es 99.'];
            }
            // Si el mismo id viene repetido, se suman las cantidades.
            $solicitado[$pid] = ($solicitado[$pid] ?? 0) + $cant;
        }

        if ($solicitado === []) {
            return ['ok' => false, 'error' => 'El carrito está vacío.'];
        }

        // --- Validar datos del cliente -------------------------------------
        $nombre = trim((string) ($cliente['nombre'] ?? ''));
        $email  = strtolower(trim((string) ($cliente['email'] ?? '')));
        if (mb_strlen($nombre) < 2) {
            return ['ok' => false, 'error' => 'Ingresá tu nombre completo.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Ingresá un email válido.'];
        }

        $pdo = Conexion::obtener();

        try {
            $pdo->beginTransaction();

            // SELECT ... FOR UPDATE bloquea las filas involucradas hasta el
            // commit, evitando que dos compras simultáneas vendan el mismo
            // último producto (condición de carrera de stock).
            $ids = array_keys($solicitado);
            $ph = [];
            $params = [];
            foreach ($ids as $i => $id) {
                $ph[] = ":i{$i}";
                $params[":i{$i}"] = $id;
            }
            $stmt = $pdo->prepare(
                'SELECT id, nombre, precio, stock
                 FROM productos
                 WHERE activo = 1 AND id IN (' . implode(', ', $ph) . ')
                 FOR UPDATE'
            );
            $stmt->execute($params);

            $enBase = [];
            foreach ($stmt->fetchAll() as $f) {
                $enBase[(int) $f['id']] = $f;
            }

            // --- Armar líneas con precios del servidor ---------------------
            $lineas = [];
            $total  = 0.0;

            foreach ($solicitado as $pid => $cant) {
                if (!isset($enBase[$pid])) {
                    $pdo->rollBack();
                    return ['ok' => false, 'error' => "Un producto del carrito ya no está disponible (ID {$pid})."];
                }
                $p = $enBase[$pid];

                if ((int) $p['stock'] < $cant) {
                    $pdo->rollBack();
                    return [
                        'ok'    => false,
                        'error' => sprintf(
                            'Stock insuficiente de "%s": quedan %d unidades.',
                            $p['nombre'],
                            (int) $p['stock']
                        ),
                    ];
                }

                $precio = (float) $p['precio'];
                $total += $precio * $cant;

                $lineas[] = [
                    'producto_id'     => $pid,
                    'producto_nombre' => $p['nombre'],
                    'precio_unitario' => $precio,
                    'cantidad'        => $cant,
                ];
            }

            // --- Cabecera --------------------------------------------------
            $codigo = self::generarCodigo();

            $stmt = $pdo->prepare(
                'INSERT INTO pedidos
                    (usuario_id, codigo, cliente_nombre, cliente_email,
                     cliente_telefono, direccion_envio, total, estado)
                 VALUES
                    (:usuario_id, :codigo, :nombre, :email,
                     :telefono, :direccion, :total, "pendiente")'
            );
            $stmt->execute([
                ':usuario_id' => $usuarioId,
                ':codigo'     => $codigo,
                ':nombre'     => $nombre,
                ':email'      => $email,
                ':telefono'   => self::oNull($cliente['telefono'] ?? null),
                ':direccion'  => self::oNull($cliente['direccion'] ?? null),
                ':total'      => number_format($total, 2, '.', ''),
            ]);
            $pedidoId = (int) $pdo->lastInsertId();

            // --- Detalle + descuento de stock ------------------------------
            $insDetalle = $pdo->prepare(
                'INSERT INTO detalle_pedidos
                    (pedido_id, producto_id, producto_nombre, precio_unitario, cantidad)
                 VALUES (:pedido, :producto, :nombre, :precio, :cantidad)'
            );
            $bajaStock = $pdo->prepare(
                'UPDATE productos SET stock = stock - :cant WHERE id = :id AND stock >= :cant'
            );

            foreach ($lineas as $l) {
                $insDetalle->execute([
                    ':pedido'   => $pedidoId,
                    ':producto' => $l['producto_id'],
                    ':nombre'   => $l['producto_nombre'],
                    ':precio'   => number_format($l['precio_unitario'], 2, '.', ''),
                    ':cantidad' => $l['cantidad'],
                ]);

                $bajaStock->execute([':cant' => $l['cantidad'], ':id' => $l['producto_id']]);
                if ($bajaStock->rowCount() !== 1) {
                    // Red de seguridad: si el UPDATE no afectó la fila, abortamos.
                    $pdo->rollBack();
                    return ['ok' => false, 'error' => 'No se pudo reservar el stock. Reintentá.'];
                }
            }

            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[pedidos.crear] ' . $e->getMessage());
            return ['ok' => false, 'error' => 'No se pudo registrar el pedido. Intentá más tarde.'];
        }

        return ['ok' => true, 'pedido' => [
            'id'     => $pedidoId,
            'codigo' => $codigo,
            'total'  => round($total, 2),
            'items'  => $lineas,
            'estado' => 'pendiente',
        ]];
    }

    /** Pedidos de un usuario, con la cantidad de artículos de cada uno. */
    public static function porUsuario(int $usuarioId, int $limite = 20): array
    {
        $stmt = Conexion::obtener()->prepare(
            'SELECT p.id, p.codigo, p.total, p.estado, p.creado_en,
                    COALESCE(SUM(d.cantidad), 0) AS articulos
             FROM pedidos p
             LEFT JOIN detalle_pedidos d ON d.pedido_id = p.id
             WHERE p.usuario_id = :uid
             GROUP BY p.id
             ORDER BY p.creado_en DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':uid', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', max(1, min($limite, 100)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** Código legible tipo HS-20260831-A1B2. */
    private static function generarCodigo(): string
    {
        return sprintf('HS-%s-%s', date('Ymd'), strtoupper(bin2hex(random_bytes(2))));
    }

    private static function oNull(mixed $v): ?string
    {
        $v = is_string($v) ? trim($v) : '';
        return $v === '' ? null : $v;
    }
}
