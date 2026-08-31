/**
 * assets/js/api.js
 * ---------------------------------------------------------------------------
 * Única puerta de salida hacia el backend. Centralizar fetch() acá permite:
 *  · adjuntar el token CSRF en todas las peticiones que modifican estado,
 *  · manejar errores HTTP en un solo lugar,
 *  · que el resto del código no sepa nada de URLs ni de headers.
 */

/** Prefijo del proyecto (soporta instalación en subcarpeta). */
const BASE = (() => {
  // Deduce la base a partir de la ruta del propio módulo:
  // .../hardwarestore/assets/js/api.js -> /hardwarestore
  const url = new URL(import.meta.url);
  return url.pathname.replace(/\/assets\/js\/[^/]*$/, '');
})();

/** Token CSRF leído del <meta>. Se actualiza si el backend manda uno nuevo. */
let csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export const getCsrf = () => csrf;
export const setCsrf = (token) => { if (token) csrf = token; };

/** Error con el código HTTP adjunto, para poder distinguir 401 de 409. */
export class ApiError extends Error {
  constructor(mensaje, status, datos = {}) {
    super(mensaje);
    this.name = 'ApiError';
    this.status = status;
    this.datos = datos;
  }
}

/**
 * Wrapper de fetch que siempre devuelve/consume JSON.
 * @param {string} ruta   ruta relativa, ej. 'api/get_productos.php'
 * @param {{metodo?:string, cuerpo?:object, senal?:AbortSignal}} opciones
 */
async function pedir(ruta, { metodo = 'GET', cuerpo = null, senal } = {}) {
  const opciones = {
    method: metodo,
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',   // manda la cookie de sesión PHP
    signal: senal,
  };

  if (cuerpo !== null) {
    opciones.headers['Content-Type'] = 'application/json';
    // El token CSRF viaja en el body para que el backend lo valide.
    opciones.body = JSON.stringify({ csrf, ...cuerpo });
  }

  let respuesta;
  try {
    respuesta = await fetch(`${BASE}/${ruta}`, opciones);
  } catch (err) {
    if (err.name === 'AbortError') throw err;      // cancelación intencional
    throw new ApiError('Sin conexión con el servidor.', 0);
  }

  let datos = {};
  try {
    datos = await respuesta.json();
  } catch {
    throw new ApiError('El servidor devolvió una respuesta inválida.', respuesta.status);
  }

  // Si el backend rotó el token (login/logout), lo adoptamos.
  setCsrf(datos.csrf);

  if (!respuesta.ok || datos.ok === false) {
    throw new ApiError(datos.error || 'Ocurrió un error inesperado.', respuesta.status, datos);
  }

  return datos;
}

/* ------------------------------- Catálogo ------------------------------- */

/**
 * Trae productos aplicando filtros.
 * @param {object} filtros {categoria, q, min, max, orden, solo_stock, pagina}
 */
export function getProductos(filtros = {}, senal) {
  const params = new URLSearchParams();
  Object.entries(filtros).forEach(([clave, valor]) => {
    if (valor !== '' && valor !== null && valor !== undefined && valor !== false) {
      params.set(clave, valor === true ? '1' : String(valor));
    }
  });
  const qs = params.toString();
  return pedir(`api/get_productos.php${qs ? `?${qs}` : ''}`, { senal });
}

export const getProducto = (id) => pedir(`api/get_productos.php?id=${encodeURIComponent(id)}`);

/* ----------------------------- Autenticación ---------------------------- */

export const authSesion  = ()      => pedir('api/auth.php', { metodo: 'POST', cuerpo: { accion: 'sesion' } });
export const authLogin   = (datos) => pedir('api/auth.php', { metodo: 'POST', cuerpo: { accion: 'login', ...datos } });
export const authRegistro= (datos) => pedir('api/auth.php', { metodo: 'POST', cuerpo: { accion: 'registro', ...datos } });
export const authLogout  = ()      => pedir('api/auth.php', { metodo: 'POST', cuerpo: { accion: 'logout' } });
export const misPedidos  = ()      => pedir('api/auth.php', { metodo: 'POST', cuerpo: { accion: 'mis_pedidos' } });

/* -------------------------------- Checkout ------------------------------ */

/**
 * Confirma la compra. Ojo: sólo se envían ids y cantidades.
 * Los precios los calcula el servidor leyendo la base de datos.
 */
export const postCheckout = (cliente, items) =>
  pedir('api/checkout.php', {
    metodo: 'POST',
    cuerpo: {
      cliente,
      items: items.map(({ id, cantidad }) => ({ producto_id: id, cantidad })),
    },
  });

export { BASE };
