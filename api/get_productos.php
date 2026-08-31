<?php
/**
 * api/get_productos.php
 * ---------------------------------------------------------------------------
 * Endpoint de lectura del catálogo. Devuelve JSON.
 *
 * GET /api/get_productos.php
 *   ?categoria=placas-video     slug de categoría
 *   &q=ryzen                    búsqueda por texto
 *   &min=100000&max=500000      rango de precio
 *   &orden=precio_asc           destacados | nuevos | precio_asc | precio_desc | nombre
 *   &solo_stock=1               excluir productos sin stock
 *   &pagina=1&por_pagina=24     paginación
 *   &id=7                       (alternativo) devuelve un único producto
 *
 * Respuesta:
 * {
 *   "ok": true,
 *   "total": 8, "pagina": 1, "por_pagina": 24,
 *   "categorias": [ {id, nombre, slug, cantidad}, ... ],
 *   "rango_precios": {"min": 154900, "max": 1899000},
 *   "productos": [ {id, nombre, marca, precio, stock, imagen, categoria, ...}, ... ]
 * }
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/modelo_productos.php';

exigir_metodo('GET');

try {
    // --- Caso 1: un producto puntual -----------------------------------
    if (isset($_GET['id'])) {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            json_error('Parámetro "id" inválido.', 422);
        }

        $producto = ProductoRepo::porId($id);
        if ($producto === null) {
            json_error('Producto no encontrado.', 404);
        }
        json_ok(['producto' => $producto]);
    }

    // --- Caso 2: listado con filtros -----------------------------------
    // Todos los valores se sanean acá; el modelo los liga como parámetros.
    $filtros = [
        'categoria'  => isset($_GET['categoria']) ? substr(trim((string) $_GET['categoria']), 0, 80) : null,
        'q'          => isset($_GET['q']) ? substr(trim((string) $_GET['q']), 0, 100) : null,
        'min'        => isset($_GET['min']) && $_GET['min'] !== '' ? (float) $_GET['min'] : null,
        'max'        => isset($_GET['max']) && $_GET['max'] !== '' ? (float) $_GET['max'] : null,
        'orden'      => isset($_GET['orden']) ? (string) $_GET['orden'] : 'destacados',
        'solo_stock' => !empty($_GET['solo_stock']),
        'pagina'     => (int) ($_GET['pagina'] ?? 1),
        'por_pagina' => (int) ($_GET['por_pagina'] ?? 24),
    ];

    $resultado = ProductoRepo::listar($filtros);

    json_ok([
        'productos'     => $resultado['items'],
        'total'         => $resultado['total'],
        'pagina'        => $resultado['pagina'],
        'por_pagina'    => $resultado['por_pagina'],
        'categorias'    => ProductoRepo::categorias(),
        'rango_precios' => ProductoRepo::rangoPrecios(),
    ]);
} catch (Throwable $e) {
    error_log('[api/get_productos] ' . $e->getMessage());
    json_error(
        APP_ENV === 'dev' ? $e->getMessage() : 'Error al obtener los productos.',
        500
    );
}
