/**
 * assets/js/app.js
 * ---------------------------------------------------------------------------
 * Punto de entrada del frontend. Orquesta los módulos:
 *
 *   api.js      -> comunicación con el backend PHP
 *   carrito.js  -> estado del carrito en localStorage
 *   ui.js       -> toasts, modales, drawer, formato
 *
 * Responsabilidades de este archivo:
 *   1. Renderizar el grid de productos desde el <template>
 *   2. Filtros/búsqueda/paginación por fetch(), sin recargar la página
 *   3. Sincronizar el carrito con la interfaz
 *   4. Login / registro / logout
 *   5. Checkout
 */

import {
  getProductos, getProducto, authSesion, authLogin, authRegistro, authLogout,
  misPedidos, postCheckout, ApiError, BASE,
} from './api.js';
import { Carrito } from './carrito.js';
import {
  $, $$, precio, debounce, toast, abrirCapa, cerrarCapa, cerrarTodo,
  marcarCargando, mostrarError,
} from './ui.js';

/* ======================================================================
   ESTADO DE LA APLICACIÓN
   ====================================================================== */

const estado = {
  filtros: { categoria: '', q: '', min: '', max: '', orden: 'destacados', solo_stock: false, pagina: 1 },
  porPagina: 12,
  total: 0,
  /** Caché id -> producto, para no volver a pedir datos al agregar al carrito. */
  catalogo: new Map(),
  usuario: null,
  /** Aborta el fetch anterior si el usuario sigue tipeando. */
  peticion: null,
};

/* Referencias al DOM (una sola búsqueda por elemento) */
const dom = {
  grid:        $('[data-grid]'),
  tpl:         $('#tpl-card'),
  conteo:      $('[data-conteo]'),
  paginacion:  $('[data-paginacion]'),
  vacio:       $('[data-vacio]'),
  formFiltros: $('[data-filtros]'),
  busqueda:    $('#q'),
  carrito:     $('[data-carrito]'),
  carritoItems:$('[data-carrito-items]'),
  carritoVacio:$('[data-carrito-vacio]'),
  carritoPie:  $('[data-carrito-footer]'),
  contador:    $('[data-carrito-contador]'),
  modalAuth:   $('#modal-auth'),
  modalCheckout: $('#modal-checkout'),
  modalPedidos:  $('#modal-pedidos'),
};

/* ======================================================================
   1. CATÁLOGO
   ====================================================================== */

/** Crea una card clonando el <template> del HTML. */
function crearCard(p) {
  const nodo = dom.tpl.content.firstElementChild.cloneNode(true);

  const img = nodo.querySelector('.card__img');
  img.src = p.imagen || `${BASE}/assets/img/placeholder.svg`;
  img.alt = p.nombre;
  img.addEventListener('error', () => { img.src = `${BASE}/assets/img/placeholder.svg`; }, { once: true });

  const t = (clave) => nodo.querySelector(`[data-tpl="${clave}"]`);

  t('categoria').textContent = p.categoria ?? '';
  t('nombre').textContent    = p.nombre;
  t('marca').textContent     = p.marca ?? '';
  t('desc').textContent      = p.descripcion ?? '';
  t('precio').textContent    = precio(p.precio);

  t('destacado').hidden = !p.destacado;
  t('agotado').hidden   = p.stock > 0;

  const ultimos = t('ultimos');
  if (p.stock > 0 && p.stock <= 5) {
    ultimos.textContent = `Últimas ${p.stock}`;
    ultimos.hidden = false;
  } else {
    ultimos.hidden = true;
  }

  const btn = t('btn');
  btn.dataset.agregar = String(p.id);
  btn.disabled = p.stock === 0;
  btn.textContent = p.stock === 0 ? 'Sin stock' : 'Agregar';

  return nodo;
}

/** Pinta el grid completo. */
function renderProductos(productos) {
  const fragmento = document.createDocumentFragment();
  productos.forEach((p) => {
    estado.catalogo.set(p.id, p);
    fragmento.appendChild(crearCard(p));
  });

  dom.grid.replaceChildren(fragmento);

  const hay = productos.length > 0;
  if (dom.vacio) dom.vacio.hidden = hay;
  if (!hay && !dom.vacio) {
    const p = document.createElement('p');
    p.className = 'vacio';
    p.dataset.vacio = '';
    p.textContent = 'No encontramos productos con esos filtros.';
    dom.grid.after(p);
    dom.vacio = p;
  }
}

/** Construye los botones de paginación. */
function renderPaginacion() {
  if (!dom.paginacion) return;

  const paginas = Math.ceil(estado.total / estado.porPagina);
  dom.paginacion.replaceChildren();
  if (paginas <= 1) return;

  const boton = (texto, pagina, { actual = false, deshabilitado = false } = {}) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'paginacion__btn';
    b.textContent = texto;
    b.disabled = deshabilitado;
    if (actual) b.setAttribute('aria-current', 'page');
    else if (!deshabilitado) b.addEventListener('click', () => cargarProductos({ pagina }));
    return b;
  };

  const actual = estado.filtros.pagina;
  dom.paginacion.appendChild(boton('‹', actual - 1, { deshabilitado: actual === 1 }));

  // Ventana de páginas alrededor de la actual (máx. 5 botones numéricos).
  const desde = Math.max(1, Math.min(actual - 2, paginas - 4));
  const hasta = Math.min(paginas, desde + 4);
  for (let i = desde; i <= hasta; i += 1) {
    dom.paginacion.appendChild(boton(String(i), i, { actual: i === actual }));
  }

  dom.paginacion.appendChild(boton('›', actual + 1, { deshabilitado: actual === paginas }));
}

/**
 * Pide productos al backend y actualiza la vista + la URL.
 * @param {object} cambios filtros que se modifican (el resto se conserva)
 */
async function cargarProductos(cambios = {}) {
  // Si cambia un filtro (y no la página), volvemos a la página 1.
  if (!('pagina' in cambios)) cambios.pagina = 1;
  Object.assign(estado.filtros, cambios);

  // Cancelamos la petición anterior: evita respuestas fuera de orden.
  estado.peticion?.abort();
  estado.peticion = new AbortController();

  marcarCargando(dom.grid, true);

  try {
    const datos = await getProductos(
      { ...estado.filtros, por_pagina: estado.porPagina },
      estado.peticion.signal
    );

    estado.total = datos.total;
    renderProductos(datos.productos);
    renderPaginacion();

    if (dom.conteo) {
      dom.conteo.textContent = `${datos.total} producto${datos.total === 1 ? '' : 's'}`;
    }

    sincronizarUrl();
  } catch (err) {
    if (err.name === 'AbortError') return;      // reemplazada por otra búsqueda
    toast(err.message, 'error');
  } finally {
    marcarCargando(dom.grid, false);
  }
}

/** Refleja los filtros en la barra de direcciones (permite copiar/compartir el link). */
function sincronizarUrl() {
  const params = new URLSearchParams();
  const { categoria, q, min, max, orden, solo_stock: soloStock, pagina } = estado.filtros;

  if (categoria) params.set('categoria', categoria);
  if (q) params.set('q', q);
  if (min !== '') params.set('min', min);
  if (max !== '') params.set('max', max);
  if (orden && orden !== 'destacados') params.set('orden', orden);
  if (soloStock) params.set('solo_stock', '1');
  if (pagina > 1) params.set('pagina', pagina);

  const qs = params.toString();
  history.replaceState(null, '', qs ? `?${qs}` : location.pathname);

  // Marca visualmente la categoría activa en el nav.
  $$('.nav__link').forEach((a) => {
    a.classList.toggle('is-activa', (a.dataset.categoria ?? '') === categoria);
  });
}

/* ---------------------- Eventos de filtros y búsqueda ------------------- */

function inicializarFiltros() {
  // Lee la URL para que el estado de JS coincida con lo que renderizó PHP.
  const p = new URLSearchParams(location.search);
  Object.assign(estado.filtros, {
    categoria: p.get('categoria') ?? '',
    q: p.get('q') ?? '',
    min: p.get('min') ?? '',
    max: p.get('max') ?? '',
    orden: p.get('orden') ?? 'destacados',
    solo_stock: p.get('solo_stock') === '1',
    pagina: Math.max(1, parseInt(p.get('pagina'), 10) || 1),
  });

  // Cachea los productos que ya vinieron renderizados por PHP.
  renderPaginacion();

  if (dom.formFiltros) {
    // Un solo listener para todos los controles del formulario.
    dom.formFiltros.addEventListener('change', (e) => {
      const campo = e.target.dataset.filtro;
      if (!campo) return;
      const valor = e.target.type === 'checkbox' ? e.target.checked : e.target.value;
      cargarProductos({ [campo]: valor });
    });

    // Los inputs de precio se aplican con debounce mientras se tipea.
    $$('[data-filtro="min"], [data-filtro="max"]', dom.formFiltros).forEach((input) => {
      input.addEventListener('input', debounce((ev) => {
        cargarProductos({ [ev.target.dataset.filtro]: ev.target.value });
      }, 500));
    });

    dom.formFiltros.addEventListener('submit', (e) => {
      e.preventDefault();          // con JS activo, nunca recargamos
      cargarProductos();
    });

    $('[data-limpiar-filtros]')?.addEventListener('click', () => {
      dom.formFiltros.reset();
      if (dom.busqueda) dom.busqueda.value = '';
      cargarProductos({ categoria: '', q: '', min: '', max: '', orden: 'destacados', solo_stock: false });
    });
  }

  // Búsqueda en vivo (debounced) + submit sin recarga.
  if (dom.busqueda) {
    dom.busqueda.addEventListener('input', debounce((e) => {
      cargarProductos({ q: e.target.value.trim() });
    }, 400));

    dom.busqueda.form?.addEventListener('submit', (e) => {
      e.preventDefault();
      cargarProductos({ q: dom.busqueda.value.trim() });
      document.querySelector('#catalogo')?.scrollIntoView({ behavior: 'smooth' });
    });
  }

  // Links de categoría del nav: interceptados para no recargar.
  $$('.nav__link').forEach((a) => {
    a.addEventListener('click', (e) => {
      e.preventDefault();
      cargarProductos({ categoria: a.dataset.categoria ?? '' });
      const select = $('[data-filtro="categoria"]');
      if (select) select.value = a.dataset.categoria ?? '';
      $('#catalogo')?.scrollIntoView({ behavior: 'smooth' });
      $('#nav-categorias')?.classList.remove('is-abierto');
    });
  });

  // Menú hamburguesa
  $('[data-toggle-nav]')?.addEventListener('click', (e) => {
    const nav = $('#nav-categorias');
    const abierto = nav?.classList.toggle('is-abierto');
    e.currentTarget.setAttribute('aria-expanded', String(!!abierto));
  });
}

/* ======================================================================
   2. CARRITO
   ====================================================================== */

/** Dibuja una línea del carrito. */
function crearItemCarrito(item) {
  const li = document.createElement('div');
  li.className = 'item';
  li.dataset.item = String(item.id);

  const img = document.createElement('img');
  img.className = 'item__img';
  img.src = item.imagen || `${BASE}/assets/img/placeholder.svg`;
  img.alt = '';
  img.loading = 'lazy';
  img.addEventListener('error', () => { img.src = `${BASE}/assets/img/placeholder.svg`; }, { once: true });

  const info = document.createElement('div');
  const nombre = document.createElement('p');
  nombre.className = 'item__nombre';
  nombre.textContent = item.nombre;          // textContent -> a prueba de XSS
  const unit = document.createElement('p');
  unit.className = 'item__precio';
  unit.textContent = `${precio(item.precio)} c/u`;

  const contador = document.createElement('div');
  contador.className = 'contador';
  const menos = document.createElement('button');
  menos.type = 'button';
  menos.dataset.menos = String(item.id);
  menos.textContent = '−';
  menos.setAttribute('aria-label', `Quitar una unidad de ${item.nombre}`);
  const valor = document.createElement('span');
  valor.className = 'contador__valor';
  valor.textContent = String(item.cantidad);
  const mas = document.createElement('button');
  mas.type = 'button';
  mas.dataset.mas = String(item.id);
  mas.textContent = '+';
  mas.disabled = item.cantidad >= Math.min(item.stock || 99, 99);
  mas.setAttribute('aria-label', `Agregar una unidad de ${item.nombre}`);
  contador.append(menos, valor, mas);

  info.append(nombre, unit, contador);

  const acciones = document.createElement('div');
  acciones.className = 'item__acciones';
  const subtotal = document.createElement('span');
  subtotal.className = 'item__subtotal';
  subtotal.textContent = precio(item.precio * item.cantidad);
  const quitar = document.createElement('button');
  quitar.type = 'button';
  quitar.className = 'item__quitar';
  quitar.dataset.quitar = String(item.id);
  quitar.textContent = 'Quitar';
  acciones.append(subtotal, quitar);

  li.append(img, info, acciones);
  return li;
}

/** Se ejecuta en cada cambio del carrito (patrón observador). */
function renderCarrito({ items, unidades, total, neto, iva }) {
  // Badge del header
  if (dom.contador) {
    dom.contador.textContent = String(unidades);
    dom.contador.hidden = unidades === 0;
  }

  if (!dom.carritoItems) return;

  const vacio = items.length === 0;
  dom.carritoItems.hidden = vacio;
  if (dom.carritoVacio) dom.carritoVacio.hidden = !vacio;
  if (dom.carritoPie) dom.carritoPie.hidden = vacio;

  if (vacio) {
    dom.carritoItems.replaceChildren();
    return;
  }

  const frag = document.createDocumentFragment();
  items.forEach((i) => frag.appendChild(crearItemCarrito(i)));
  dom.carritoItems.replaceChildren(frag);

  const set = (sel, valor) => { const el = $(sel); if (el) el.textContent = precio(valor); };
  set('[data-carrito-neto]', neto);
  set('[data-carrito-iva]', iva);
  set('[data-carrito-total]', total);
}

function inicializarCarrito() {
  Carrito.alCambiar(renderCarrito);

  // --- Agregar al carrito (delegación de eventos en todo el documento) ---
  // Un solo listener sirve para las cards de PHP y para las que crea JS.
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-agregar]');
    if (!btn) return;

    const id = Number(btn.dataset.agregar);
    let producto = estado.catalogo.get(id);

    // Si la card vino del render de PHP, puede no estar en la caché:
    // la pedimos al backend antes de agregarla.
    if (!producto) {
      btn.disabled = true;
      try {
        const { producto: p } = await getProducto(id);
        producto = p;
        estado.catalogo.set(id, p);
      } catch (err) {
        toast(err.message, 'error');
        btn.disabled = false;
        return;
      }
      btn.disabled = false;
    }

    const r = Carrito.agregar(producto, 1);
    if (!r.ok) {
      toast(r.motivo ?? 'No se pudo agregar el producto.', 'error');
      return;
    }
    toast(r.motivo ?? `“${producto.nombre}” agregado al carrito.`, 'exito');

    // Micro-feedback en el botón
    const original = btn.textContent;
    btn.textContent = '✓ Agregado';
    setTimeout(() => { btn.textContent = original; }, 1100);
  });

  // --- Controles dentro del drawer ---
  dom.carritoItems?.addEventListener('click', (e) => {
    const mas = e.target.closest('[data-mas]');
    const menos = e.target.closest('[data-menos]');
    const quitar = e.target.closest('[data-quitar]');

    if (mas) {
      const id = Number(mas.dataset.mas);
      const r = Carrito.fijarCantidad(id, Carrito.cantidad(id) + 1);
      if (r.ok && r.cantidad === r.tope) toast(`Máximo disponible: ${r.tope}.`, 'info', 2000);
    } else if (menos) {
      const id = Number(menos.dataset.menos);
      Carrito.fijarCantidad(id, Carrito.cantidad(id) - 1);
    } else if (quitar) {
      Carrito.quitar(Number(quitar.dataset.quitar));
      toast('Producto quitado del carrito.', 'info', 2000);
    }
  });

  // --- Abrir / cerrar drawer ---
  $$('[data-abrir-carrito]').forEach((b) => b.addEventListener('click', () => abrirCapa(dom.carrito)));
  $$('[data-cerrar-carrito]').forEach((b) => b.addEventListener('click', () => cerrarCapa(dom.carrito)));

  $('[data-vaciar-carrito]')?.addEventListener('click', () => {
    if (Carrito.estaVacio()) return;
    Carrito.vaciar();
    toast('Carrito vacío.', 'info', 2000);
  });
}

/* ======================================================================
   3. AUTENTICACIÓN
   ====================================================================== */

function inicializarAuth() {
  // Abrir el modal de login/registro
  $$('[data-abrir-modal]').forEach((b) => {
    b.addEventListener('click', () => abrirCapa($(`#${b.dataset.abrirModal}`)));
  });
  $$('[data-cerrar-modal]').forEach((b) => {
    b.addEventListener('click', () => cerrarCapa(b.closest('[data-modal]')));
  });

  // Pestañas Ingresar / Crear cuenta
  $$('.tabs__btn').forEach((tab) => {
    tab.addEventListener('click', () => {
      const objetivo = tab.dataset.tab;
      $$('.tabs__btn').forEach((t) => {
        const activa = t === tab;
        t.classList.toggle('is-activa', activa);
        t.setAttribute('aria-selected', String(activa));
      });
      $$('[data-panel]').forEach((panel) => { panel.hidden = panel.dataset.panel !== objetivo; });
    });
  });

  // --- Login ---
  $('[data-form="login"]')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.currentTarget;
    mostrarError(form, null);
    await enviarForm(form, async (datos) => {
      const r = await authLogin({ email: datos.email, password: datos.password });
      aplicarSesion(r.usuario);
      cerrarTodo();
      toast(`¡Hola, ${r.usuario.nombre.split(' ')[0]}!`, 'exito');
    });
  });

  // --- Registro ---
  $('[data-form="registro"]')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.currentTarget;
    mostrarError(form, null);
    await enviarForm(form, async (datos) => {
      const r = await authRegistro(datos);
      aplicarSesion(r.usuario);
      cerrarTodo();
      toast(r.mensaje ?? 'Cuenta creada.', 'exito');
    });
  });

  // --- Logout y "mis pedidos" (menú de cuenta) ---
  document.addEventListener('click', async (e) => {
    const accion = e.target.closest('[data-accion]')?.dataset.accion;
    if (!accion) return;

    if (accion === 'logout') {
      try {
        await authLogout();
        toast('Sesión cerrada.', 'info');
        location.reload();     // el header se re-renderiza en el servidor
      } catch (err) {
        toast(err.message, 'error');
      }
    }

    if (accion === 'mis-pedidos') {
      try {
        const { pedidos } = await misPedidos();
        renderPedidos(pedidos);
        abrirCapa(dom.modalPedidos);
      } catch (err) {
        toast(err.message, 'error');
      }
    }
  });

  // --- Menú desplegable de cuenta ---
  const menu = $('[data-menu]');
  if (menu) {
    const toggle = $('[data-menu-toggle]', menu);
    const panel = $('[data-menu-panel]', menu);
    toggle.addEventListener('click', () => {
      const abrir = panel.hidden;
      panel.hidden = !abrir;
      toggle.setAttribute('aria-expanded', String(abrir));
    });
    document.addEventListener('click', (e) => {
      if (!menu.contains(e.target)) {
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }
}

function aplicarSesion(usuario) {
  estado.usuario = usuario ?? null;
  // Precarga los datos del cliente en el checkout.
  if (usuario) {
    const set = (sel, v) => { const el = $(sel); if (el && v) el.value = v; };
    set('#co-nombre', usuario.nombre);
    set('#co-email', usuario.email);
    set('#co-tel', usuario.telefono);
    set('#co-dir', usuario.direccion);
  }
}

function renderPedidos(pedidos) {
  const cont = $('[data-pedidos-lista]');
  if (!cont) return;

  if (!pedidos?.length) {
    cont.replaceChildren(Object.assign(document.createElement('p'), {
      className: 'vacio',
      textContent: 'Todavía no hiciste ningún pedido.',
    }));
    return;
  }

  const frag = document.createDocumentFragment();
  pedidos.forEach((p) => {
    const fila = document.createElement('div');
    fila.className = 'pedido';

    const izq = document.createElement('div');
    izq.append(
      Object.assign(document.createElement('strong'), { textContent: p.codigo }),
      document.createElement('br'),
      Object.assign(document.createElement('small'), {
        textContent: `${new Date(p.creado_en.replace(' ', 'T')).toLocaleDateString('es-AR')} · ${p.articulos} artículo(s)`,
      })
    );

    const der = document.createElement('div');
    der.append(
      Object.assign(document.createElement('span'), { className: 'pedido__estado', textContent: p.estado }),
      document.createTextNode(' '),
      Object.assign(document.createElement('strong'), { textContent: precio(p.total) })
    );

    fila.append(izq, der);
    frag.appendChild(fila);
  });
  cont.replaceChildren(frag);
}

/* ======================================================================
   4. CHECKOUT
   ====================================================================== */

function inicializarCheckout() {
  $('[data-ir-checkout]')?.addEventListener('click', () => {
    if (Carrito.estaVacio()) {
      toast('Tu carrito está vacío.', 'error');
      return;
    }
    renderResumen();
    // Volvemos a mostrar el formulario si venimos de una compra anterior.
    $('[data-form="checkout"]').hidden = false;
    $('[data-checkout-exito]').hidden = true;
    cerrarCapa(dom.carrito);
    abrirCapa(dom.modalCheckout);
  });

  $('[data-form="checkout"]')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.currentTarget;
    mostrarError(form, null);

    await enviarForm(form, async (datos) => {
      const r = await postCheckout(datos, Carrito.items());

      // Pedido grabado: vaciamos el carrito y mostramos el comprobante.
      Carrito.vaciar();
      $('[data-exito-codigo]').textContent = r.pedido.codigo;
      $('[data-exito-total]').textContent = precio(r.pedido.total);
      form.hidden = true;
      $('[data-checkout-exito]').hidden = false;
      toast('¡Pedido confirmado!', 'exito');

      // Refresca el catálogo: el stock cambió en la base.
      cargarProductos({ pagina: estado.filtros.pagina });
    });
  });
}

/** Resumen de la compra dentro del modal de checkout. */
function renderResumen() {
  const cont = $('[data-checkout-resumen]');
  if (!cont) return;

  const { items, neto, iva, total } = { items: Carrito.items(), ...Carrito.totales() };
  const frag = document.createDocumentFragment();

  items.forEach((i) => {
    const fila = document.createElement('div');
    fila.className = 'resumen__fila';
    fila.append(
      Object.assign(document.createElement('span'), { textContent: `${i.cantidad} × ${i.nombre}` }),
      Object.assign(document.createElement('span'), { textContent: precio(i.precio * i.cantidad) })
    );
    frag.appendChild(fila);
  });

  const linea = (etiqueta, valor, clase = 'resumen__fila') => {
    const f = document.createElement('div');
    f.className = clase;
    f.append(
      Object.assign(document.createElement('span'), { textContent: etiqueta }),
      Object.assign(document.createElement('span'), { textContent: precio(valor) })
    );
    return f;
  };

  frag.append(
    linea('Subtotal (sin IVA)', neto),
    linea('IVA 21 %', iva),
    linea('Total', total, 'resumen__fila resumen__total')
  );

  cont.replaceChildren(frag);
}

/* ======================================================================
   HELPERS DE FORMULARIOS
   ====================================================================== */

/**
 * Envuelve el envío de un formulario: validación nativa, estado "enviando",
 * y traducción de ApiError a un mensaje visible.
 * @param {HTMLFormElement} form
 * @param {(datos:Record<string,string>) => Promise<void>} accion
 */
async function enviarForm(form, accion) {
  if (!form.checkValidity()) {
    form.reportValidity();
    return;
  }

  const boton = form.querySelector('button[type="submit"]');
  const textoOriginal = boton?.textContent;
  if (boton) { boton.disabled = true; boton.textContent = 'Enviando…'; }

  const datos = Object.fromEntries(new FormData(form).entries());

  try {
    await accion(datos);
    if (form.dataset.form !== 'checkout') form.reset();
  } catch (err) {
    const mensaje = err instanceof ApiError ? err.message : 'Error inesperado. Reintentá.';
    mostrarError(form, mensaje);
    // 419 = token vencido: hay que recargar para obtener uno nuevo.
    if (err instanceof ApiError && err.status === 419) {
      toast('Tu sesión expiró. Recargando…', 'error');
      setTimeout(() => location.reload(), 1800);
    }
  } finally {
    if (boton) { boton.disabled = false; boton.textContent = textoOriginal; }
  }
}

/* ======================================================================
   ARRANQUE
   ====================================================================== */

async function iniciar() {
  inicializarFiltros();
  inicializarCarrito();
  inicializarAuth();
  inicializarCheckout();

  // Consulta el estado de sesión (y renueva el token CSRF).
  try {
    const { usuario } = await authSesion();
    aplicarSesion(usuario);
  } catch {
    /* Sin backend disponible: la tienda sigue navegable en modo lectura. */
  }
}

// El módulo se ejecuta diferido, así que el DOM ya está listo;
// igual chequeamos por si se mueve el <script>.
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', iniciar, { once: true });
} else {
  iniciar();
}
