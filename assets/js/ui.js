/**
 * assets/js/ui.js
 * ---------------------------------------------------------------------------
 * Piezas de interfaz reutilizables: formato, toasts, overlay, modales y drawer.
 * No sabe nada de productos ni del carrito: sólo mueve el DOM.
 */

/* ----------------------------- Utilidades ------------------------------- */

export const $  = (sel, ctx = document) => ctx.querySelector(sel);
export const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

const formateador = new Intl.NumberFormat('es-AR', {
  style: 'currency',
  currency: 'ARS',
  minimumFractionDigits: 2,
});

/** 1234.5 -> "$ 1.234,50" */
export const precio = (n) => formateador.format(Number(n) || 0);

/** Retrasa la ejecución hasta que pasen `ms` sin nuevas llamadas. */
export function debounce(fn, ms = 350) {
  let t;
  return (...args) => {
    clearTimeout(t);
    t = setTimeout(() => fn(...args), ms);
  };
}

/* ------------------------------- Toasts --------------------------------- */

const contenedorToasts = $('[data-toasts]');

/**
 * Notificación efímera.
 * @param {string} mensaje
 * @param {'info'|'exito'|'error'} tipo
 */
export function toast(mensaje, tipo = 'info', ms = 3200) {
  if (!contenedorToasts) return;

  const el = document.createElement('div');
  el.className = `toast toast--${tipo}`;
  el.setAttribute('role', tipo === 'error' ? 'alert' : 'status');
  el.textContent = mensaje;             // textContent, nunca innerHTML -> sin XSS
  contenedorToasts.appendChild(el);

  const cerrar = () => {
    el.classList.add('toast--saliendo');
    el.addEventListener('animationend', () => el.remove(), { once: true });
  };
  setTimeout(cerrar, ms);
}

/* ---------------------- Overlay compartido ------------------------------ */

const overlay = $('[data-overlay]');
let capasAbiertas = 0;

function mostrarOverlay() {
  capasAbiertas += 1;
  if (overlay) overlay.hidden = false;
  document.body.style.overflow = 'hidden';   // bloquea el scroll de fondo
}

function ocultarOverlay() {
  capasAbiertas = Math.max(0, capasAbiertas - 1);
  if (capasAbiertas === 0) {
    if (overlay) overlay.hidden = true;
    document.body.style.overflow = '';
  }
}

/* ---------------------- Capas: modales y drawer ------------------------- */

/** Pila de capas abiertas, para que Escape cierre siempre la última. */
const pila = [];
let ultimoFoco = null;

/**
 * Abre un elemento como capa modal.
 * Gestiona foco, overlay y cierre por Escape / clic en overlay.
 */
export function abrirCapa(el) {
  if (!el || pila.includes(el)) return;   // ya está abierta o no existe

  if (pila.length === 0) ultimoFoco = document.activeElement;

  el.hidden = false;
  pila.push(el);
  mostrarOverlay();

  // Foco al primer control útil de la capa (accesibilidad de teclado).
  const foco = el.querySelector(
    'input:not([type=hidden]):not([disabled]), select, textarea, button:not([disabled])'
  );
  (foco ?? el).focus?.({ preventScroll: true });

  atraparFoco(el);
}

export function cerrarCapa(el) {
  const i = pila.indexOf(el);
  if (i === -1) return;

  el.hidden = true;
  pila.splice(i, 1);
  ocultarOverlay();

  if (pila.length === 0 && ultimoFoco?.isConnected) {
    ultimoFoco.focus?.({ preventScroll: true });
    ultimoFoco = null;
  }
}

export function cerrarTodo() {
  [...pila].reverse().forEach(cerrarCapa);
}

export const capaAbierta = (el) => pila.includes(el);

/**
 * Mantiene el foco dentro de la capa mientras esté abierta
 * (requisito WAI-ARIA para dialogs modales).
 */
function atraparFoco(el) {
  const manejar = (e) => {
    if (e.key !== 'Tab' || el.hidden) return;

    const focusables = $$(
      'a[href], button:not([disabled]), input:not([type=hidden]):not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])',
      el
    ).filter((n) => n.offsetParent !== null);

    if (focusables.length === 0) return;
    const primero = focusables[0];
    const ultimo = focusables[focusables.length - 1];

    if (e.shiftKey && document.activeElement === primero) {
      e.preventDefault();
      ultimo.focus();
    } else if (!e.shiftKey && document.activeElement === ultimo) {
      e.preventDefault();
      primero.focus();
    }
  };

  el.addEventListener('keydown', manejar);
}

/* --------------------- Cierres globales (una sola vez) ------------------ */

// Escape cierra la capa más reciente.
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && pila.length > 0) {
    cerrarCapa(pila[pila.length - 1]);
  }
});

// Clic en el overlay cierra todo.
overlay?.addEventListener('click', cerrarTodo);

// Clic fuera de .modal__caja (en el padding del modal) también cierra.
document.addEventListener('click', (e) => {
  const modal = e.target.closest?.('[data-modal]');
  if (modal && e.target === modal) cerrarCapa(modal);
});

/* ---------------------- Estado de carga del grid ------------------------ */

export function marcarCargando(el, cargando) {
  if (el) el.setAttribute('aria-busy', cargando ? 'true' : 'false');
}

/* --------------------------- Errores de forms --------------------------- */

export function mostrarError(form, mensaje) {
  const caja = form?.querySelector('[data-form-error]');
  if (!caja) return;
  caja.textContent = mensaje ?? '';
  caja.hidden = !mensaje;
}
