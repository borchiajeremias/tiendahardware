/**
 * assets/js/carrito.js
 * ---------------------------------------------------------------------------
 * Carrito del lado del cliente, persistido en localStorage.
 *
 * Diseño:
 *  · Estado inmutable hacia afuera: `Carrito.items()` devuelve una copia.
 *  · Patrón observador: quien quiera enterarse de los cambios se suscribe con
 *    `Carrito.alCambiar(fn)`. Así la UI no tiene que llamarse a sí misma.
 *  · Lo que se guarda es el mínimo: id, nombre, precio, imagen y cantidad.
 *    El precio guardado es SOLO para mostrar; el checkout lo revalida en el
 *    servidor, así que manipular localStorage no cambia lo que se cobra.
 *  · El evento 'storage' sincroniza pestañas abiertas del mismo sitio.
 */

const CLAVE = 'hardstore.carrito.v1';
const MAX_UNIDADES = 99;

/** @typedef {{id:number,nombre:string,precio:number,imagen:string,stock:number,cantidad:number}} ItemCarrito */

let items = cargar();
const suscriptores = new Set();

/* --------------------------- Persistencia ------------------------------- */

function cargar() {
  try {
    const crudo = localStorage.getItem(CLAVE);
    if (!crudo) return [];
    const datos = JSON.parse(crudo);
    if (!Array.isArray(datos)) return [];
    // Saneamos: localStorage es editable por el usuario, nunca confiar ciegamente.
    return datos
      .map((i) => ({
        id: Number(i.id),
        nombre: String(i.nombre ?? ''),
        precio: Number(i.precio) || 0,
        imagen: String(i.imagen ?? ''),
        stock: Number(i.stock) || 0,
        cantidad: Math.min(Math.max(parseInt(i.cantidad, 10) || 1, 1), MAX_UNIDADES),
      }))
      .filter((i) => Number.isInteger(i.id) && i.id > 0);
  } catch {
    // JSON corrupto o localStorage bloqueado (modo privado): arrancamos vacío.
    return [];
  }
}

function guardar() {
  try {
    localStorage.setItem(CLAVE, JSON.stringify(items));
  } catch {
    /* Cuota llena o almacenamiento deshabilitado: el carrito sigue en memoria. */
  }
  notificar();
}

function notificar() {
  const snapshot = Object.freeze({ items: items.map((i) => ({ ...i })), ...totales() });
  suscriptores.forEach((fn) => fn(snapshot));
}

/* ------------------------------ Cálculos -------------------------------- */

function totales() {
  const bruto = items.reduce((acc, i) => acc + i.precio * i.cantidad, 0);
  const unidades = items.reduce((acc, i) => acc + i.cantidad, 0);
  const IVA = 0.21;
  return {
    unidades,
    total: redondear(bruto),
    neto: redondear(bruto / (1 + IVA)),   // los precios de vidriera ya incluyen IVA
    iva: redondear(bruto - bruto / (1 + IVA)),
  };
}

const redondear = (n) => Math.round(n * 100) / 100;

/* ------------------------------- API ------------------------------------ */

export const Carrito = {
  /** Copia defensiva del contenido actual. @returns {ItemCarrito[]} */
  items: () => items.map((i) => ({ ...i })),

  totales,

  cantidad: (id) => items.find((i) => i.id === Number(id))?.cantidad ?? 0,

  estaVacio: () => items.length === 0,

  /**
   * Agrega un producto (o suma unidades si ya estaba).
   * @param {{id:number,nombre:string,precio:number,imagen?:string,stock?:number}} producto
   * @param {number} cantidad
   * @returns {{ok:boolean, motivo?:string, cantidad?:number}}
   */
  agregar(producto, cantidad = 1) {
    const id = Number(producto.id);
    const stock = Number(producto.stock ?? MAX_UNIDADES);
    if (!Number.isInteger(id) || id <= 0) return { ok: false, motivo: 'Producto inválido.' };
    if (stock <= 0) return { ok: false, motivo: 'Producto sin stock.' };

    const existente = items.find((i) => i.id === id);
    const deseada = (existente?.cantidad ?? 0) + Math.max(1, parseInt(cantidad, 10) || 1);
    const tope = Math.min(stock, MAX_UNIDADES);

    if (deseada > tope) {
      // Ya tiene el máximo disponible: avisamos en vez de sumar silenciosamente.
      if ((existente?.cantidad ?? 0) >= tope) {
        return { ok: false, motivo: `Sólo hay ${tope} unidad(es) disponibles.` };
      }
      if (existente) existente.cantidad = tope; else items.push(nuevoItem(producto, tope, stock));
      guardar();
      return { ok: true, cantidad: tope, motivo: `Ajustado al stock disponible (${tope}).` };
    }

    if (existente) {
      existente.cantidad = deseada;
      // Refrescamos precio/nombre por si el catálogo cambió desde la última visita.
      existente.precio = Number(producto.precio) || existente.precio;
      existente.nombre = producto.nombre || existente.nombre;
      existente.stock = stock;
    } else {
      items.push(nuevoItem(producto, deseada, stock));
    }

    guardar();
    return { ok: true, cantidad: deseada };
  },

  /** Fija una cantidad exacta. Si es <= 0, elimina la línea. */
  fijarCantidad(id, cantidad) {
    id = Number(id);
    const item = items.find((i) => i.id === id);
    if (!item) return { ok: false };

    const n = parseInt(cantidad, 10) || 0;
    if (n <= 0) return this.quitar(id);

    const tope = Math.min(item.stock || MAX_UNIDADES, MAX_UNIDADES);
    item.cantidad = Math.min(n, tope);
    guardar();
    return { ok: true, cantidad: item.cantidad, tope };
  },

  quitar(id) {
    const antes = items.length;
    items = items.filter((i) => i.id !== Number(id));
    if (items.length !== antes) guardar();
    return { ok: items.length !== antes };
  },

  vaciar() {
    items = [];
    guardar();
  },

  /**
   * Se suscribe a los cambios. Devuelve la función para desuscribirse.
   * El callback se ejecuta una vez de inmediato con el estado actual.
   */
  alCambiar(fn) {
    suscriptores.add(fn);
    fn(Object.freeze({ items: this.items(), ...totales() }));
    return () => suscriptores.delete(fn);
  },
};

function nuevoItem(p, cantidad, stock) {
  return {
    id: Number(p.id),
    nombre: String(p.nombre ?? ''),
    precio: Number(p.precio) || 0,
    imagen: String(p.imagen ?? ''),
    stock,
    cantidad,
  };
}

/* ------------------- Sincronización entre pestañas ---------------------- */
window.addEventListener('storage', (e) => {
  if (e.key === CLAVE) {
    items = cargar();
    notificar();
  }
});
