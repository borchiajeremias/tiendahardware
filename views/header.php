<?php
/**
 * views/header.php
 * ---------------------------------------------------------------------------
 * Cabecera común: <head>, barra superior, navegación de categorías,
 * búsqueda y acceso al carrito / cuenta.
 *
 * Variables esperadas (definidas por la vista que lo incluye):
 *   $titulo      string
 *   $categorias  array de ProductoRepo::categorias()
 *   $usuario     array|null
 *   $csrf        string
 */

declare(strict_types=1);

$titulo     = $titulo     ?? APP_NAME;
$categorias = $categorias ?? [];
$usuario    = $usuario    ?? null;
$csrf       = $csrf       ?? '';
$base       = APP_URL;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="HardStore — componentes de PC, periféricos y equipos armados. Envíos a todo el país.">
    <meta name="theme-color" content="#0b1120">

    <!-- El token CSRF viaja en un meta para que app.js lo lea sin inline scripts -->
    <meta name="csrf-token" content="<?= e($csrf) ?>">

    <title><?= e($titulo) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="<?= $base ?>/assets/css/styles.css">
    <link rel="icon" href="<?= $base ?>/assets/img/favicon.svg" type="image/svg+xml">
</head>
<body>

<!-- Enlace de accesibilidad para usuarios de teclado / lectores de pantalla -->
<a class="skip-link" href="#contenido">Saltar al contenido</a>

<!-- ====================== BARRA DE AVISOS ====================== -->
<div class="topbar">
    <div class="contenedor topbar__inner">
        <span>Envíos a todo el país · 12 cuotas sin interés · Armado sin cargo</span>
        <span class="topbar__tel" aria-label="Teléfono de contacto">
            <svg viewBox="0 0 24 24" aria-hidden="true" class="ico"><path d="M6.6 10.8c1.2 2.4 3.2 4.4 5.6 5.6l1.9-1.9c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.5.6.6 0 1 .4 1 1V19c0 .6-.4 1-1 1C10.6 20 4 13.4 4 5c0-.6.4-1 1-1h3.1c.6 0 1 .4 1 1 0 1.2.2 2.4.6 3.5.1.4 0 .8-.2 1l-1.9 1.3z"/></svg>
            0800-333-HARD
        </span>
    </div>
</div>

<!-- ====================== HEADER PRINCIPAL ====================== -->
<header class="header" id="header">
    <div class="contenedor header__inner">

        <a class="logo" href="<?= $base ?>/index.php" aria-label="HardStore, inicio">
            <svg class="logo__ico" viewBox="0 0 32 32" aria-hidden="true">
                <rect x="3" y="3" width="26" height="26" rx="6"/>
                <rect x="10" y="10" width="12" height="12" rx="2" class="logo__chip"/>
                <path d="M16 3v4M16 25v4M3 16h4M25 16h4" class="logo__pins"/>
            </svg>
            <span class="logo__texto">Hard<strong>Store</strong></span>
        </a>

        <!-- Búsqueda: es un <form> real, así funciona incluso sin JS -->
        <form class="buscador" role="search" action="<?= $base ?>/index.php" method="get">
            <label class="sr-only" for="q">Buscar productos</label>
            <svg class="buscador__ico" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M10 4a6 6 0 104 10.5l4.3 4.3 1.4-1.4-4.3-4.3A6 6 0 0010 4zm0 2a4 4 0 110 8 4 4 0 010-8z"/>
            </svg>
            <input type="search" id="q" name="q" class="buscador__input"
                   placeholder="Buscar procesadores, placas de video, SSD…"
                   autocomplete="off"
                   value="<?= e($_GET['q'] ?? '') ?>">
            <button class="buscador__btn" type="submit">Buscar</button>
        </form>

        <div class="header__acciones">

            <!-- Cuenta -->
            <?php if ($usuario !== null): ?>
                <div class="menu-cuenta" data-menu>
                    <button class="btn-icono" type="button" data-menu-toggle
                            aria-expanded="false" aria-haspopup="true">
                        <svg viewBox="0 0 24 24" aria-hidden="true" class="ico"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-4.4 0-8 2.2-8 5v1h16v-1c0-2.8-3.6-5-8-5z"/></svg>
                        <span class="btn-icono__txt"><?= e(explode(' ', (string) $usuario['nombre'])[0]) ?></span>
                    </button>
                    <div class="menu-cuenta__panel" data-menu-panel hidden>
                        <p class="menu-cuenta__mail"><?= e($usuario['email']) ?></p>
                        <button class="menu-cuenta__item" type="button" data-accion="mis-pedidos">Mis pedidos</button>
                        <button class="menu-cuenta__item menu-cuenta__item--peligro" type="button" data-accion="logout">Cerrar sesión</button>
                    </div>
                </div>
            <?php else: ?>
                <button class="btn-icono" type="button" data-abrir-modal="modal-auth">
                    <svg viewBox="0 0 24 24" aria-hidden="true" class="ico"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-4.4 0-8 2.2-8 5v1h16v-1c0-2.8-3.6-5-8-5z"/></svg>
                    <span class="btn-icono__txt">Ingresar</span>
                </button>
            <?php endif; ?>

            <!-- Carrito -->
            <button class="btn-carrito" type="button" data-abrir-carrito
                    aria-label="Abrir carrito de compras">
                <svg viewBox="0 0 24 24" aria-hidden="true" class="ico"><path d="M7 4h-3v2h2l2.6 9.6c.2.8.9 1.4 1.8 1.4h8.4v-2h-8.2L10 13h9l2-8H6.4L7 4zm2 15a1.8 1.8 0 100 3.6A1.8 1.8 0 009 19zm9 0a1.8 1.8 0 100 3.6A1.8 1.8 0 0018 19z"/></svg>
                <span class="btn-carrito__txt">Carrito</span>
                <span class="badge" data-carrito-contador hidden>0</span>
            </button>

            <!-- Hamburguesa (solo móvil) -->
            <button class="btn-icono btn-icono--menu" type="button" data-toggle-nav
                    aria-expanded="false" aria-controls="nav-categorias" aria-label="Abrir menú">
                <svg viewBox="0 0 24 24" aria-hidden="true" class="ico"><path d="M3 6h18v2H3zM3 11h18v2H3zM3 16h18v2H3z"/></svg>
            </button>
        </div>
    </div>

    <!-- ====================== NAVEGACIÓN DE CATEGORÍAS ====================== -->
    <nav class="nav" id="nav-categorias" aria-label="Categorías de productos">
        <div class="contenedor nav__inner">
            <a class="nav__link is-activa" href="<?= $base ?>/index.php" data-categoria="">
                Todos los productos
            </a>
            <?php foreach ($categorias as $c): ?>
                <a class="nav__link"
                   href="<?= $base ?>/index.php?categoria=<?= e($c['slug']) ?>"
                   data-categoria="<?= e($c['slug']) ?>">
                    <?= e($c['nombre']) ?>
                    <span class="nav__cant"><?= (int) $c['cantidad'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>
</header>
