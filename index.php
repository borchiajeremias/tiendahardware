<?php
/**
 * index.php
 * ---------------------------------------------------------------------------
 * Vista principal del catálogo.
 *
 * Estrategia de renderizado (progressive enhancement):
 *  1. PHP hace el primer render del listado con los filtros de la URL.
 *     -> la página funciona sin JavaScript y es indexable por buscadores.
 *  2. app.js toma el control: a partir de ahí los filtros, la búsqueda y la
 *     paginación se resuelven con fetch() contra /api/get_productos.php,
 *     sin recargar la página.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/modelo_productos.php';

sesion_iniciar();

$usuario = usuario_actual();
$csrf    = csrf_token();

// --- Filtros tomados de la query string ------------------------------------
$filtros = [
    'categoria'  => isset($_GET['categoria']) ? trim((string) $_GET['categoria']) : null,
    'q'          => isset($_GET['q']) ? trim((string) $_GET['q']) : null,
    'min'        => ($_GET['min'] ?? '') !== '' ? (float) $_GET['min'] : null,
    'max'        => ($_GET['max'] ?? '') !== '' ? (float) $_GET['max'] : null,
    'orden'      => (string) ($_GET['orden'] ?? 'destacados'),
    'solo_stock' => !empty($_GET['solo_stock']),
    'pagina'     => (int) ($_GET['pagina'] ?? 1),
    'por_pagina' => 12,
];

$errorBd = null;

try {
    $categorias = ProductoRepo::categorias();
    $rango      = ProductoRepo::rangoPrecios();
    $resultado  = ProductoRepo::listar($filtros);
} catch (Throwable $e) {
    // Si la base no está creada todavía, mostramos un aviso claro
    // en lugar de una pantalla en blanco.
    $errorBd    = $e->getMessage();
    $categorias = [];
    $rango      = ['min' => 0, 'max' => 0];
    $resultado  = ['items' => [], 'total' => 0, 'pagina' => 1, 'por_pagina' => 12];
}

$titulo = $filtros['categoria']
    ? ucfirst(str_replace('-', ' ', $filtros['categoria'])) . ' · ' . APP_NAME
    : APP_NAME . ' · Hardware, periféricos y PCs armadas';

require __DIR__ . '/views/header.php';
?>

<main id="contenido">

    <?php if ($errorBd !== null): ?>
        <div class="contenedor">
            <div class="alerta alerta--error">
                <strong>No se pudo leer la base de datos.</strong>
                <p>Importá <code>sql/schema.sql</code> y revisá las credenciales en <code>config/config.php</code>.</p>
                <?php if (APP_ENV === 'dev'): ?>
                    <pre class="alerta__detalle"><?= e($errorBd) ?></pre>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ====================== HERO ====================== -->
    <section class="hero">
        <div class="contenedor hero__inner">
            <div class="hero__texto">
                <p class="hero__kicker">Nueva generación AM5 · DDR5 · PCIe 5.0</p>
                <h1 class="hero__titulo">Armá la PC que siempre quisiste</h1>
                <p class="hero__bajada">
                    Componentes con garantía oficial, asesoramiento técnico real y armado
                    sin cargo. Envíos a todo el país en 24 a 72 horas.
                </p>
                <div class="hero__acciones">
                    <a class="btn btn--primario" href="#catalogo">Ver catálogo</a>
                    <a class="btn btn--fantasma" href="?categoria=equipos-armados">Equipos armados</a>
                </div>
            </div>
            <ul class="hero__beneficios">
                <li><strong>12</strong><span>cuotas sin interés</span></li>
                <li><strong>24h</strong><span>despacho promedio</span></li>
                <li><strong>3 años</strong><span>de garantía en SSD</span></li>
            </ul>
        </div>
    </section>

    <!-- ====================== CATÁLOGO ====================== -->
    <section class="catalogo contenedor" id="catalogo">

        <!-- ---------- Barra de filtros ---------- -->
        <aside class="filtros" aria-label="Filtros de búsqueda">
            <form class="filtros__form" data-filtros method="get" action="<?= APP_URL ?>/index.php">

                <div class="filtros__grupo">
                    <h2 class="filtros__titulo">Categoría</h2>
                    <select class="select" name="categoria" data-filtro="categoria" aria-label="Categoría">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?= e($c['slug']) ?>"
                                <?= $filtros['categoria'] === $c['slug'] ? 'selected' : '' ?>>
                                <?= e($c['nombre']) ?> (<?= (int) $c['cantidad'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filtros__grupo">
                    <h2 class="filtros__titulo">Precio</h2>
                    <div class="filtros__precio">
                        <label class="sr-only" for="f-min">Precio mínimo</label>
                        <input type="number" id="f-min" name="min" class="input input--num"
                               placeholder="Mín." min="0" step="1000"
                               data-filtro="min" value="<?= e((string) ($_GET['min'] ?? '')) ?>">
                        <span aria-hidden="true">—</span>
                        <label class="sr-only" for="f-max">Precio máximo</label>
                        <input type="number" id="f-max" name="max" class="input input--num"
                               placeholder="Máx." min="0" step="1000"
                               data-filtro="max" value="<?= e((string) ($_GET['max'] ?? '')) ?>">
                    </div>
                    <p class="filtros__nota">
                        Catálogo: <?= precio($rango['min']) ?> a <?= precio($rango['max']) ?>
                    </p>
                </div>

                <div class="filtros__grupo">
                    <h2 class="filtros__titulo">Ordenar por</h2>
                    <select class="select" name="orden" data-filtro="orden" aria-label="Ordenar por">
                        <?php
                        $ordenes = [
                            'destacados'  => 'Destacados',
                            'nuevos'      => 'Más nuevos',
                            'precio_asc'  => 'Menor precio',
                            'precio_desc' => 'Mayor precio',
                            'nombre'      => 'Nombre (A-Z)',
                        ];
                        foreach ($ordenes as $valor => $etiqueta): ?>
                            <option value="<?= e($valor) ?>"
                                <?= $filtros['orden'] === $valor ? 'selected' : '' ?>>
                                <?= e($etiqueta) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filtros__grupo">
                    <label class="check">
                        <input type="checkbox" name="solo_stock" value="1"
                               data-filtro="solo_stock" <?= $filtros['solo_stock'] ? 'checked' : '' ?>>
                        <span>Sólo con stock disponible</span>
                    </label>
                </div>

                <!-- Fallback para navegadores sin JS -->
                <noscript><button class="btn btn--primario btn--bloque" type="submit">Aplicar filtros</button></noscript>

                <button class="btn btn--texto btn--bloque" type="button" data-limpiar-filtros>
                    Limpiar filtros
                </button>
            </form>
        </aside>

        <!-- ---------- Grid de productos ---------- -->
        <div class="resultados">
            <header class="resultados__header">
                <h2 class="resultados__titulo">
                    <?php if ($filtros['q']): ?>
                        Resultados para “<?= e($filtros['q']) ?>”
                    <?php elseif ($filtros['categoria']): ?>
                        <?= e(ucfirst(str_replace('-', ' ', $filtros['categoria']))) ?>
                    <?php else: ?>
                        Todos los productos
                    <?php endif; ?>
                </h2>
                <p class="resultados__conteo" data-conteo>
                    <?= (int) $resultado['total'] ?> producto<?= $resultado['total'] === 1 ? '' : 's' ?>
                </p>
            </header>

            <!--
                El grid se rellena en el primer render con PHP y luego lo
                reemplaza app.js. La plantilla <template> de abajo es la que
                usa JavaScript para crear cada card, así el markup vive en un
                solo lugar en vez de estar duplicado en un string de JS.
            -->
            <div class="grid" data-grid aria-live="polite" aria-busy="false">
                <?php foreach ($resultado['items'] as $p): ?>
                    <article class="card">
                        <div class="card__media">
                            <img class="card__img"
                                 src="<?= e($p['imagen'] ?: 'assets/img/placeholder.svg') ?>"
                                 alt="<?= e($p['nombre']) ?>" loading="lazy" width="320" height="240"
                                 onerror="this.src='assets/img/placeholder.svg'">
                            <?php if ($p['destacado']): ?>
                                <span class="etiqueta etiqueta--destacado">Destacado</span>
                            <?php endif; ?>
                            <?php if ($p['stock'] === 0): ?>
                                <span class="etiqueta etiqueta--agotado">Sin stock</span>
                            <?php elseif ($p['stock'] <= 5): ?>
                                <span class="etiqueta etiqueta--ultimos">Últimas <?= (int) $p['stock'] ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="card__cuerpo">
                            <p class="card__categoria"><?= e($p['categoria']) ?></p>
                            <h3 class="card__nombre"><?= e($p['nombre']) ?></h3>
                            <p class="card__marca"><?= e($p['marca'] ?? '') ?></p>
                            <p class="card__desc"><?= e($p['descripcion'] ?? '') ?></p>
                            <div class="card__pie">
                                <p class="card__precio"><?= precio($p['precio']) ?></p>
                                <button class="btn btn--primario card__btn"
                                        type="button"
                                        data-agregar="<?= (int) $p['id'] ?>"
                                        <?= $p['stock'] === 0 ? 'disabled' : '' ?>>
                                    <?= $p['stock'] === 0 ? 'Sin stock' : 'Agregar' ?>
                                </button>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($resultado['total'] === 0 && $errorBd === null): ?>
                <p class="vacio" data-vacio>No encontramos productos con esos filtros.</p>
            <?php endif; ?>

            <!-- Paginación: se reconstruye desde JS -->
            <nav class="paginacion" data-paginacion aria-label="Paginación"></nav>
        </div>
    </section>

    <!-- ====================== FRANJA DE CONFIANZA ====================== -->
    <section class="franja">
        <div class="contenedor franja__grid">
            <div class="franja__item">
                <h3>Armado sin cargo</h3>
                <p>Comprás los componentes y te los entregamos ensamblados y testeados.</p>
            </div>
            <div class="franja__item">
                <h3>Garantía oficial</h3>
                <p>Todos los productos con factura A o B y garantía del fabricante.</p>
            </div>
            <div class="franja__item">
                <h3>Soporte real</h3>
                <p>Técnicos que te responden por WhatsApp antes y después de comprar.</p>
            </div>
        </div>
    </section>
</main>

<!-- ====================== TEMPLATE DE CARD (usado por app.js) ====================== -->
<template id="tpl-card">
    <article class="card">
        <div class="card__media">
            <img class="card__img" alt="" loading="lazy" width="320" height="240">
            <span class="etiqueta etiqueta--destacado" data-tpl="destacado" hidden>Destacado</span>
            <span class="etiqueta etiqueta--agotado" data-tpl="agotado" hidden>Sin stock</span>
            <span class="etiqueta etiqueta--ultimos" data-tpl="ultimos" hidden></span>
        </div>
        <div class="card__cuerpo">
            <p class="card__categoria" data-tpl="categoria"></p>
            <h3 class="card__nombre" data-tpl="nombre"></h3>
            <p class="card__marca" data-tpl="marca"></p>
            <p class="card__desc" data-tpl="desc"></p>
            <div class="card__pie">
                <p class="card__precio" data-tpl="precio"></p>
                <button class="btn btn--primario card__btn" type="button" data-tpl="btn">Agregar</button>
            </div>
        </div>
    </article>
</template>

<?php require __DIR__ . '/views/footer.php'; ?>
