<?php
/**
 * includes/modelo_productos.php
 * ---------------------------------------------------------------------------
 * Capa de acceso a datos de productos y categorías.
 * Ninguna consulta concatena valores del usuario: todo va por placeholders.
 */

declare(strict_types=1);

require_once __DIR__ . '/conexion.php';

final class ProductoRepo
{
    /** Columnas por las que se permite ordenar (whitelist anti-inyección). */
    private const ORDENES = [
        'nuevos'       => 'p.creado_en DESC, p.id DESC',
        'precio_asc'   => 'p.precio ASC',
        'precio_desc'  => 'p.precio DESC',
        'nombre'       => 'p.nombre ASC',
        'destacados'   => 'p.destacado DESC, p.id DESC',
    ];

    /**
     * Lista productos aplicando filtros opcionales.
     *
     * @param array{
     *   categoria?:string|null, q?:string|null, min?:float|null, max?:float|null,
     *   orden?:string|null, solo_stock?:bool, pagina?:int, por_pagina?:int
     * } $f
     * @return array{items:array<int,array<string,mixed>>, total:int, pagina:int, por_pagina:int}
     */
    public static function listar(array $f = []): array
    {
        $pdo = Conexion::obtener();

        // --- Construcción dinámica pero SEGURA del WHERE -------------------
        // Cada condición agrega su propio placeholder; los valores nunca
        // se interpolan en el string SQL.
        $where  = ['p.activo = 1'];
        $params = [];

        if (!empty($f['categoria'])) {
            $where[] = 'c.slug = :categoria';
            $params[':categoria'] = (string) $f['categoria'];
        }

        if (!empty($f['q'])) {
            // LIKE sobre nombre/marca/descripción. El comodín va en el VALOR,
            // no en la consulta, así que sigue siendo un parámetro ligado.
            $where[] = '(p.nombre LIKE :q OR p.marca LIKE :q OR p.descripcion LIKE :q)';
            $params[':q'] = '%' . self::escaparLike((string) $f['q']) . '%';
        }

        if (isset($f['min']) && $f['min'] !== '' && $f['min'] !== null) {
            $where[] = 'p.precio >= :min';
            $params[':min'] = (float) $f['min'];
        }

        if (isset($f['max']) && $f['max'] !== '' && $f['max'] !== null) {
            $where[] = 'p.precio <= :max';
            $params[':max'] = (float) $f['max'];
        }

        if (!empty($f['solo_stock'])) {
            $where[] = 'p.stock > 0';
        }

        $sqlWhere = 'WHERE ' . implode(' AND ', $where);

        // Orden: solo valores de la whitelist llegan al SQL.
        $orden = self::ORDENES[$f['orden'] ?? ''] ?? self::ORDENES['destacados'];

        // Paginación acotada para que nadie pida 1.000.000 de filas.
        $porPagina = min(max((int) ($f['por_pagina'] ?? 24), 1), 60);
        $pagina    = max((int) ($f['pagina'] ?? 1), 1);
        $offset    = ($pagina - 1) * $porPagina;

        // --- Total (para la paginación) ------------------------------------
        $sqlTotal = "SELECT COUNT(*) FROM productos p
                     INNER JOIN categorias c ON c.id = p.categoria_id
                     {$sqlWhere}";
        $stmt = $pdo->prepare($sqlTotal);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // --- Página de resultados ------------------------------------------
        $sql = "SELECT  p.id, p.nombre, p.slug, p.marca, p.descripcion,
                        p.precio, p.stock, p.imagen, p.destacado,
                        c.id AS categoria_id, c.nombre AS categoria, c.slug AS categoria_slug
                FROM    productos p
                INNER JOIN categorias c ON c.id = p.categoria_id
                {$sqlWhere}
                ORDER BY {$orden}
                LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $clave => $valor) {
            $stmt->bindValue($clave, $valor);
        }
        // LIMIT/OFFSET requieren bind explícito como entero.
        $stmt->bindValue(':limit', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = array_map([self::class, 'normalizar'], $stmt->fetchAll());

        return [
            'items'      => $items,
            'total'      => $total,
            'pagina'     => $pagina,
            'por_pagina' => $porPagina,
        ];
    }

    /** Trae un producto activo por id, o null. */
    public static function porId(int $id): ?array
    {
        $stmt = Conexion::obtener()->prepare(
            'SELECT p.*, c.nombre AS categoria, c.slug AS categoria_slug
             FROM productos p
             INNER JOIN categorias c ON c.id = p.categoria_id
             WHERE p.id = :id AND p.activo = 1'
        );
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch();

        return $fila ? self::normalizar($fila) : null;
    }

    /**
     * Trae varios productos por id en una sola consulta.
     * Genera un placeholder por cada id (:p0, :p1, ...) -> sigue siendo preparada.
     *
     * @param int[] $ids
     * @return array<int,array<string,mixed>> Indexado por id de producto
     */
    public static function porIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $placeholders[] = ":p{$i}";
            $params[":p{$i}"] = $id;
        }

        $sql = 'SELECT id, nombre, precio, stock, activo
                FROM productos
                WHERE activo = 1 AND id IN (' . implode(', ', $placeholders) . ')';

        $stmt = Conexion::obtener()->prepare($sql);
        $stmt->execute($params);

        $salida = [];
        foreach ($stmt->fetchAll() as $fila) {
            $salida[(int) $fila['id']] = $fila;
        }
        return $salida;
    }

    /** Rango de precios del catálogo, útil para inicializar el filtro. */
    public static function rangoPrecios(): array
    {
        $fila = Conexion::obtener()
            ->query('SELECT MIN(precio) AS min, MAX(precio) AS max FROM productos WHERE activo = 1')
            ->fetch();

        return [
            'min' => (float) ($fila['min'] ?? 0),
            'max' => (float) ($fila['max'] ?? 0),
        ];
    }

    /** Categorías con su cantidad de productos activos. */
    public static function categorias(): array
    {
        $sql = 'SELECT c.id, c.nombre, c.slug, COUNT(p.id) AS cantidad
                FROM categorias c
                LEFT JOIN productos p ON p.categoria_id = c.id AND p.activo = 1
                GROUP BY c.id, c.nombre, c.slug
                ORDER BY c.nombre';

        return array_map(static function (array $c): array {
            $c['id']       = (int) $c['id'];
            $c['cantidad'] = (int) $c['cantidad'];
            return $c;
        }, Conexion::obtener()->query($sql)->fetchAll());
    }

    /** Castea tipos para que el JSON salga con números y no con strings. */
    private static function normalizar(array $p): array
    {
        $p['id']           = (int) $p['id'];
        $p['categoria_id'] = (int) $p['categoria_id'];
        $p['precio']       = (float) $p['precio'];
        $p['stock']        = (int) $p['stock'];
        $p['destacado']    = (bool) $p['destacado'];
        return $p;
    }

    /** Neutraliza los comodines de LIKE escritos por el usuario. */
    private static function escaparLike(string $valor): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $valor);
    }
}
