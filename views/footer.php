<?php
/**
 * views/footer.php
 * ---------------------------------------------------------------------------
 * Pie del sitio + componentes globales de UI que viven fuera del flujo:
 * drawer del carrito, modales (auth y checkout), contenedor de toasts.
 *
 * Todos se renderizan vacíos/ocultos y los llena app.js.
 */
declare(strict_types=1);
$base = APP_URL;
?>

<!-- ====================== FOOTER ====================== -->
<footer class="footer">
    <div class="contenedor footer__grid">
        <div>
            <h3 class="footer__titulo">Hard<strong>Store</strong></h3>
            <p class="footer__texto">
                Componentes, periféricos y equipos armados. Asesoramiento técnico real,
                garantía oficial y armado sin cargo.
            </p>
        </div>
        <div>
            <h4 class="footer__sub">Tienda</h4>
            <ul class="footer__lista">
                <li><a href="<?= $base ?>/index.php?categoria=procesadores">Procesadores</a></li>
                <li><a href="<?= $base ?>/index.php?categoria=placas-video">Placas de video</a></li>
                <li><a href="<?= $base ?>/index.php?categoria=equipos-armados">Equipos armados</a></li>
                <li><a href="<?= $base ?>/index.php?orden=nuevos">Novedades</a></li>
            </ul>
        </div>
        <div>
            <h4 class="footer__sub">Ayuda</h4>
            <ul class="footer__lista">
                <li><a href="#">Formas de pago</a></li>
                <li><a href="#">Envíos y retiros</a></li>
                <li><a href="#">Garantía y devoluciones</a></li>
                <li><a href="#">Contacto</a></li>
            </ul>
        </div>
        <div>
            <h4 class="footer__sub">Contacto</h4>
            <ul class="footer__lista">
                <li>Av. Siempreviva 742, CABA</li>
                <li>Lun a Vie 9 a 19 · Sáb 9 a 13</li>
                <li><a href="mailto:ventas@hardstore.test">ventas@hardstore.test</a></li>
                <li>0800-333-HARD</li>
            </ul>
        </div>
    </div>
    <div class="contenedor footer__legal">
        <p>© <?= date('Y') ?> <?= e(APP_NAME) ?>. Precios en pesos argentinos con IVA incluido.</p>
        <p>Proyecto demostrativo — HTML5, CSS3, JavaScript, PHP y MySQL.</p>
    </div>
</footer>


<!-- ====================== DRAWER DEL CARRITO ====================== -->
<div class="overlay" data-overlay hidden></div>

<aside class="carrito" id="carrito" data-carrito
       role="dialog" aria-modal="true" aria-labelledby="carrito-titulo" hidden>
    <header class="carrito__header">
        <h2 class="carrito__titulo" id="carrito-titulo">Tu carrito</h2>
        <button class="btn-cerrar" type="button" data-cerrar-carrito aria-label="Cerrar carrito">&times;</button>
    </header>

    <!-- Lista de ítems: la genera app.js -->
    <div class="carrito__items" data-carrito-items></div>

    <!-- Estado vacío -->
    <div class="carrito__vacio" data-carrito-vacio>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4h-3v2h2l2.6 9.6c.2.8.9 1.4 1.8 1.4h8.4v-2h-8.2L10 13h9l2-8H6.4L7 4z"/></svg>
        <p>Todavía no agregaste productos.</p>
        <button class="btn btn--secundario" type="button" data-cerrar-carrito>Ver catálogo</button>
    </div>

    <footer class="carrito__footer" data-carrito-footer hidden>
        <dl class="carrito__totales">
            <div><dt>Subtotal (sin IVA)</dt><dd data-carrito-neto>$ 0,00</dd></div>
            <div><dt>IVA 21 %</dt><dd data-carrito-iva>$ 0,00</dd></div>
            <div class="carrito__total"><dt>Total</dt><dd data-carrito-total>$ 0,00</dd></div>
        </dl>
        <button class="btn btn--primario btn--bloque" type="button" data-ir-checkout>
            Finalizar compra
        </button>
        <button class="btn btn--texto btn--bloque" type="button" data-vaciar-carrito>
            Vaciar carrito
        </button>
    </footer>
</aside>


<!-- ====================== MODAL: INGRESAR / REGISTRARSE ====================== -->
<div class="modal" id="modal-auth" data-modal role="dialog" aria-modal="true"
     aria-labelledby="auth-titulo" hidden>
    <div class="modal__caja">
        <button class="btn-cerrar modal__cerrar" type="button" data-cerrar-modal aria-label="Cerrar">&times;</button>

        <div class="tabs" role="tablist">
            <button class="tabs__btn is-activa" type="button" role="tab"
                    aria-selected="true" data-tab="login">Ingresar</button>
            <button class="tabs__btn" type="button" role="tab"
                    aria-selected="false" data-tab="registro">Crear cuenta</button>
        </div>

        <h2 class="sr-only" id="auth-titulo">Ingresar o crear una cuenta</h2>

        <!-- LOGIN -->
        <form class="form" data-form="login" data-panel="login" novalidate>
            <div class="campo">
                <label for="login-email">Email</label>
                <input type="email" id="login-email" name="email" required
                       autocomplete="email" placeholder="tu@email.com">
            </div>
            <div class="campo">
                <label for="login-pass">Contraseña</label>
                <input type="password" id="login-pass" name="password" required
                       autocomplete="current-password" minlength="8" placeholder="••••••••">
            </div>
            <p class="form__error" data-form-error role="alert" hidden></p>
            <button class="btn btn--primario btn--bloque" type="submit">Ingresar</button>
            <p class="form__ayuda">Cuenta de prueba: <code>admin@hardstore.test</code> / <code>Admin1234</code></p>
        </form>

        <!-- REGISTRO -->
        <form class="form" data-form="registro" data-panel="registro" novalidate hidden>
            <div class="campo">
                <label for="reg-nombre">Nombre y apellido</label>
                <input type="text" id="reg-nombre" name="nombre" required
                       autocomplete="name" minlength="2" maxlength="100">
            </div>
            <div class="campo">
                <label for="reg-email">Email</label>
                <input type="email" id="reg-email" name="email" required autocomplete="email">
            </div>
            <div class="campo-fila">
                <div class="campo">
                    <label for="reg-pass">Contraseña</label>
                    <input type="password" id="reg-pass" name="password" required
                           autocomplete="new-password" minlength="8">
                    <small class="campo__hint">Mínimo 8 caracteres, con letras y números.</small>
                </div>
                <div class="campo">
                    <label for="reg-tel">Teléfono <span class="campo__opt">(opcional)</span></label>
                    <input type="tel" id="reg-tel" name="telefono" autocomplete="tel">
                </div>
            </div>
            <div class="campo">
                <label for="reg-dir">Dirección <span class="campo__opt">(opcional)</span></label>
                <input type="text" id="reg-dir" name="direccion" autocomplete="street-address">
            </div>
            <p class="form__error" data-form-error role="alert" hidden></p>
            <button class="btn btn--primario btn--bloque" type="submit">Crear cuenta</button>
        </form>
    </div>
</div>


<!-- ====================== MODAL: CHECKOUT ====================== -->
<div class="modal" id="modal-checkout" data-modal role="dialog" aria-modal="true"
     aria-labelledby="checkout-titulo" hidden>
    <div class="modal__caja">
        <button class="btn-cerrar modal__cerrar" type="button" data-cerrar-modal aria-label="Cerrar">&times;</button>
        <h2 class="modal__titulo" id="checkout-titulo">Finalizar compra</h2>

        <form class="form" data-form="checkout" novalidate>
            <div class="campo-fila">
                <div class="campo">
                    <label for="co-nombre">Nombre y apellido</label>
                    <input type="text" id="co-nombre" name="nombre" required minlength="2">
                </div>
                <div class="campo">
                    <label for="co-email">Email</label>
                    <input type="email" id="co-email" name="email" required>
                </div>
            </div>
            <div class="campo-fila">
                <div class="campo">
                    <label for="co-tel">Teléfono</label>
                    <input type="tel" id="co-tel" name="telefono">
                </div>
                <div class="campo">
                    <label for="co-dir">Dirección de envío</label>
                    <input type="text" id="co-dir" name="direccion">
                </div>
            </div>

            <div class="resumen" data-checkout-resumen></div>

            <p class="form__error" data-form-error role="alert" hidden></p>
            <button class="btn btn--primario btn--bloque" type="submit">Confirmar pedido</button>
            <p class="form__ayuda">Demo: no se procesan pagos reales. El pedido se graba en MySQL.</p>
        </form>

        <!-- Comprobante que se muestra al confirmar -->
        <div class="exito" data-checkout-exito hidden>
            <div class="exito__ico" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M9 16.2l-3.5-3.5L4 14.2 9 19.2 20 8.2l-1.5-1.5z"/></svg>
            </div>
            <h3>¡Pedido confirmado!</h3>
            <p>Tu código de seguimiento es <strong data-exito-codigo></strong>.</p>
            <p class="exito__total">Total: <strong data-exito-total></strong></p>
            <button class="btn btn--primario" type="button" data-cerrar-modal>Seguir comprando</button>
        </div>
    </div>
</div>


<!-- ====================== MODAL: MIS PEDIDOS ====================== -->
<div class="modal" id="modal-pedidos" data-modal role="dialog" aria-modal="true"
     aria-labelledby="pedidos-titulo" hidden>
    <div class="modal__caja">
        <button class="btn-cerrar modal__cerrar" type="button" data-cerrar-modal aria-label="Cerrar">&times;</button>
        <h2 class="modal__titulo" id="pedidos-titulo">Mis pedidos</h2>
        <div data-pedidos-lista></div>
    </div>
</div>


<!-- ====================== TOASTS ====================== -->
<div class="toasts" data-toasts aria-live="polite" aria-atomic="false"></div>


<!-- app.js es un módulo ES: se carga diferido y con scope propio -->
<script type="module" src="<?= $base ?>/assets/js/app.js"></script>
</body>
</html>
