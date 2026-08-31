# HardStore — Tienda de hardware informático

E-commerce completo hecho con **HTML5 + CSS3 + JavaScript vainilla (ES6 modules) + PHP 8 (PDO) + MySQL 8**.
Sin frameworks, sin Composer, sin build step: se copia en el servidor y funciona.

---

## 1. Estructura de carpetas

```
hardwarestore/
│
├── index.php                  # Vista principal (catálogo). Render inicial en PHP.
├── .htaccess                  # Hardening: cabeceras, CSP, compresión, caché
├── README.md
│
├── config/
│   ├── config.php             # ← ÚNICO archivo a editar al instalar (credenciales, entorno)
│   └── .htaccess              # Deniega acceso HTTP a esta carpeta
│
├── includes/                  # Lógica de servidor (no se accede por URL)
│   ├── conexion.php           # PDO singleton + manejo de excepciones
│   ├── funciones.php          # Helpers: e(), precio(), JSON, sesiones, CSRF
│   ├── modelo_productos.php   # ProductoRepo: listar / porId / categorías / filtros
│   ├── modelo_usuarios.php    # UsuarioRepo: registrar / login / logout
│   └── modelo_pedidos.php     # PedidoRepo: crear pedido en transacción
│
├── api/                       # Endpoints JSON consumidos por fetch()
│   ├── get_productos.php      # GET  · catálogo con filtros y paginación
│   ├── auth.php               # POST · sesion | registro | login | logout | mis_pedidos
│   └── checkout.php           # POST · confirma la compra y graba el pedido
│
├── views/
│   ├── header.php             # <head>, topbar, header, búsqueda, nav de categorías
│   └── footer.php             # Footer + drawer del carrito + modales + toasts
│
├── assets/
│   ├── css/styles.css         # Variables CSS, Grid/Flexbox, responsive, dark UI
│   ├── js/
│   │   ├── app.js             # Orquestador: catálogo, filtros, auth, checkout
│   │   ├── api.js             # Capa de fetch + CSRF + manejo de errores
│   │   ├── carrito.js         # Estado del carrito en localStorage (observable)
│   │   └── ui.js              # Toasts, modales, drawer, foco, formato de moneda
│   └── img/                   # Imágenes SVG de los productos + placeholder + favicon
│
└── sql/
    └── schema.sql             # Tablas, FKs, índices y datos de prueba
```

**Criterio de la separación:** `api/` sólo valida entrada y serializa salida; toda la
lógica de datos vive en `includes/modelo_*.php`; las vistas no consultan la base
directamente más que a través de los repositorios.

---

## 2. Instalación

### Requisitos
- PHP **8.1+** con extensión `pdo_mysql`
- MySQL **8.0+** o MariaDB 10.4+
- Apache con `mod_rewrite`/`mod_headers` (o Nginx, replicando el `.htaccess`)

### Pasos

1. **Copiar el proyecto** en el document root:
   - XAMPP: `C:\xampp\htdocs\hardwarestore`
   - Laragon: `C:\laragon\www\hardwarestore`
   - Linux: `/var/www/html/hardwarestore`

2. **Importar la base de datos**:
   ```bash
   mysql -u root -p < sql/schema.sql
   ```
   O desde phpMyAdmin: *Importar → seleccionar `sql/schema.sql` → Ejecutar*.
   El script crea la base `hardwarestore`, las 5 tablas y carga 8 productos en 6 categorías.

3. **Configurar credenciales** en `config/config.php`:
   ```php
   define('DB_USER', 'root');
   define('DB_PASS', '');          // tu contraseña de MySQL
   define('APP_URL', '/hardwarestore');  // subcarpeta; usar '' si está en la raíz
   ```

4. **Abrir** `http://localhost/hardwarestore/`

5. **Antes de subir a producción**: poner `define('APP_ENV', 'prod');` en `config/config.php`.

### Cuenta de prueba
```
admin@hardstore.test  /  Admin1234
```

---

## 3. Modelo de datos

```
categorias ──1:N──> productos
                        │
usuarios ──1:N──> pedidos ──1:N──> detalle_pedidos <──N:1── productos
```

| Tabla | Notas de diseño |
|---|---|
| `usuarios` | `email` UNIQUE · `password_hash` VARCHAR(255) para bcrypt/argon2 · rol `cliente`/`admin` |
| `categorias` | `slug` UNIQUE, es lo que viaja en la URL y en los filtros |
| `productos` | `precio` DECIMAL(12,2) (nunca FLOAT para dinero) · índice FULLTEXT para búsqueda · `CHECK (precio >= 0)` |
| `pedidos` | `usuario_id` NULLABLE → permite compra como invitado · `ON DELETE SET NULL` conserva el histórico |
| `detalle_pedidos` | Guarda **snapshot** de nombre y precio: si el producto cambia de precio, el pedido viejo no muta · `subtotal` es columna GENERADA STORED |

---

## 4. Decisiones de seguridad

| Riesgo | Mitigación en este proyecto |
|---|---|
| **Inyección SQL** | PDO con `ATTR_EMULATE_PREPARES => false` (prepared statements reales). Ningún valor se concatena. El `ORDER BY` usa una *whitelist* de columnas y `LIMIT/OFFSET` van con `PARAM_INT`. Para `IN (...)` se genera un placeholder por id. |
| **XSS** | Toda salida de PHP pasa por `e()` (`htmlspecialchars` con `ENT_QUOTES`). En JS se usa exclusivamente `textContent` y clonado de `<template>`; **nunca** `innerHTML` con datos. |
| **CSRF** | Token por sesión en un `<meta>`, enviado en el body de todo POST y verificado con `hash_equals()`. Cookie de sesión `SameSite=Lax`. |
| **Manipulación de precios** | El carrito del navegador **no** manda precios: sólo `{producto_id, cantidad}`. `PedidoRepo::crear()` relee precio y stock desde MySQL. Editar `localStorage` no cambia lo que se cobra. |
| **Contraseñas** | `password_hash()` / `password_verify()` + `password_needs_rehash()`. En login fallido se verifica contra un hash señuelo para no filtrar por *timing* si el email existe. Mensaje de error genérico. |
| **Session fixation** | `session_regenerate_id(true)` al iniciar sesión; cookie `HttpOnly`. |
| **Carrera de stock** | El alta del pedido corre en transacción con `SELECT … FOR UPDATE` sobre los productos, más un `UPDATE … WHERE stock >= :cant` como red de seguridad. |
| **Fuga de información** | Los mensajes de excepción sólo se muestran con `APP_ENV = 'dev'`; en `prod` van a `error_log`. `config/`, `includes/`, `sql/` y `views/` tienen su propio `.htaccess` que deniega el acceso. |

---

## 5. Cómo funciona el frontend

**Progressive enhancement.** `index.php` renderiza el catálogo en el servidor,
así que la página es indexable y funciona sin JavaScript (los filtros son un
`<form method="get">` real). Cuando `app.js` carga, intercepta los eventos y a
partir de ahí todo se resuelve con `fetch()` contra `api/`:

- Búsqueda y filtros: *debounced*, con `AbortController` para descartar respuestas viejas.
- La URL se actualiza con `history.replaceState()` → los filtros son compartibles.
- Carrito: módulo observable sobre `localStorage`, sincronizado entre pestañas
  mediante el evento `storage`.
- Modales y drawer: gestión de foco (*focus trap*), cierre con `Escape`, overlay
  compartido y pila de capas.

### API rápida

```
GET  api/get_productos.php?categoria=procesadores&q=ryzen&min=100000&max=600000
                          &orden=precio_asc&solo_stock=1&pagina=1&por_pagina=12
GET  api/get_productos.php?id=4

POST api/auth.php      {"accion":"login","email":"...","password":"...","csrf":"..."}
POST api/auth.php      {"accion":"registro","nombre":"...","email":"...","password":"...","csrf":"..."}
POST api/auth.php      {"accion":"logout","csrf":"..."}
POST api/auth.php      {"accion":"mis_pedidos"}

POST api/checkout.php  {"csrf":"...","cliente":{...},"items":[{"producto_id":1,"cantidad":2}]}
```

Todas las respuestas comparten forma: `{"ok":true, ...}` o `{"ok":false,"error":"mensaje"}`.

---

## 6. Próximos pasos sugeridos

- Panel `/admin` con CRUD de productos y gestión de estados de pedido (el rol `admin` ya existe).
- Recuperación de contraseña por email con token de un uso.
- Rate limiting en `api/auth.php` (por ejemplo, 5 intentos por IP cada 15 minutos).
- Integración de pasarela de pago (Mercado Pago Checkout Pro).
- Subida de imágenes reales de productos con validación de MIME y tamaño.
