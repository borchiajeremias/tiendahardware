-- ============================================================================
--  HardStore  |  Esquema de base de datos
--  Motor: MySQL 8.x / MariaDB 10.4+   ·   Charset: utf8mb4
--
--  Ejecutar con:  mysql -u root -p < sql/schema.sql
-- ============================================================================

DROP DATABASE IF EXISTS hardwarestore;
CREATE DATABASE hardwarestore
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE hardwarestore;

-- ----------------------------------------------------------------------------
--  usuarios
--  La contraseña NUNCA se guarda en texto plano: se almacena el hash
--  generado por password_hash() de PHP (bcrypt/argon2 -> hasta 255 chars).
-- ----------------------------------------------------------------------------
CREATE TABLE usuarios (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nombre          VARCHAR(100)    NOT NULL,
    email           VARCHAR(150)    NOT NULL,
    password_hash   VARCHAR(255)    NOT NULL,
    telefono        VARCHAR(30)     DEFAULT NULL,
    direccion       VARCHAR(255)    DEFAULT NULL,
    rol             ENUM('cliente','admin') NOT NULL DEFAULT 'cliente',
    activo          TINYINT(1)      NOT NULL DEFAULT 1,
    creado_en       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
--  categorias
--  'slug' se usa en la URL y en los filtros del frontend.
-- ----------------------------------------------------------------------------
CREATE TABLE categorias (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nombre          VARCHAR(80)     NOT NULL,
    slug            VARCHAR(80)     NOT NULL,
    descripcion     VARCHAR(255)    DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categorias_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
--  productos
--  · precio en DECIMAL(12,2): jamás usar FLOAT para dinero.
--  · categoria_id ON DELETE RESTRICT: no se borra una categoría con productos.
-- ----------------------------------------------------------------------------
CREATE TABLE productos (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    categoria_id    INT UNSIGNED    NOT NULL,
    nombre          VARCHAR(150)    NOT NULL,
    slug            VARCHAR(170)    NOT NULL,
    marca           VARCHAR(60)     DEFAULT NULL,
    descripcion     TEXT            DEFAULT NULL,
    precio          DECIMAL(12,2)   NOT NULL,
    stock           INT UNSIGNED    NOT NULL DEFAULT 0,
    imagen          VARCHAR(255)    DEFAULT NULL,
    destacado       TINYINT(1)      NOT NULL DEFAULT 0,
    activo          TINYINT(1)      NOT NULL DEFAULT 1,
    creado_en       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_productos_slug (slug),
    KEY idx_productos_categoria (categoria_id),
    KEY idx_productos_precio (precio),
    FULLTEXT KEY ft_productos_busqueda (nombre, marca, descripcion),
    CONSTRAINT fk_productos_categoria
        FOREIGN KEY (categoria_id) REFERENCES categorias (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_productos_precio CHECK (precio >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
--  pedidos  (cabecera)
--  usuario_id es NULLABLE para permitir compras como invitado.
--  ON DELETE SET NULL: si se borra el usuario, el pedido histórico sobrevive.
-- ----------------------------------------------------------------------------
CREATE TABLE pedidos (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    usuario_id      INT UNSIGNED    DEFAULT NULL,
    codigo          VARCHAR(20)     NOT NULL,           -- ej: HS-20260831-0007
    cliente_nombre  VARCHAR(120)    NOT NULL,
    cliente_email   VARCHAR(150)    NOT NULL,
    cliente_telefono VARCHAR(30)    DEFAULT NULL,
    direccion_envio VARCHAR(255)    DEFAULT NULL,
    total           DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    estado          ENUM('pendiente','pagado','enviado','entregado','cancelado')
                    NOT NULL DEFAULT 'pendiente',
    creado_en       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pedidos_codigo (codigo),
    KEY idx_pedidos_usuario (usuario_id),
    CONSTRAINT fk_pedidos_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
--  detalle_pedidos  (líneas)
--  Se copian nombre y precio del producto al momento de la compra
--  (snapshot histórico: si mañana cambia el precio, el pedido no muta).
--  subtotal es una columna GENERADA -> imposible que quede desincronizada.
-- ----------------------------------------------------------------------------
CREATE TABLE detalle_pedidos (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    pedido_id       INT UNSIGNED    NOT NULL,
    producto_id     INT UNSIGNED    DEFAULT NULL,
    producto_nombre VARCHAR(150)    NOT NULL,
    precio_unitario DECIMAL(12,2)   NOT NULL,
    cantidad        INT UNSIGNED    NOT NULL,
    subtotal        DECIMAL(12,2)   AS (precio_unitario * cantidad) STORED,
    PRIMARY KEY (id),
    KEY idx_detalle_pedido (pedido_id),
    KEY idx_detalle_producto (producto_id),
    CONSTRAINT fk_detalle_pedido
        FOREIGN KEY (pedido_id) REFERENCES pedidos (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_detalle_producto
        FOREIGN KEY (producto_id) REFERENCES productos (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT chk_detalle_cantidad CHECK (cantidad > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
--  DATOS DE PRUEBA (seeds)
-- ============================================================================

INSERT INTO categorias (nombre, slug, descripcion) VALUES
('Procesadores',  'procesadores',  'CPUs AMD e Intel para gaming y workstation'),
('Placas de Video','placas-video', 'GPUs dedicadas NVIDIA y AMD'),
('Memorias RAM',  'memorias-ram',  'Módulos DDR4 y DDR5'),
('Almacenamiento','almacenamiento','Discos SSD NVMe, SATA y HDD'),
('Periféricos',   'perifericos',   'Teclados, mouses, headsets y monitores'),
('Equipos Armados','equipos-armados','PCs completas listas para usar');

INSERT INTO productos
    (categoria_id, nombre, slug, marca, descripcion, precio, stock, imagen, destacado) VALUES
(1, 'AMD Ryzen 7 7800X3D', 'amd-ryzen-7-7800x3d', 'AMD',
 '8 núcleos / 16 hilos, 4.2 GHz base y 5.0 GHz boost, 96 MB de caché 3D V-Cache. El rey del gaming en socket AM5.',
 489999.00, 12, 'assets/img/ryzen-7800x3d.svg', 1),

(2, 'NVIDIA GeForce RTX 4070 Super 12GB', 'rtx-4070-super-12gb', 'ASUS',
 'Placa de video con 12 GB GDDR6X, 7168 CUDA cores, DLSS 3 y Ray Tracing de 3ra generación. Ideal para 1440p.',
 1149000.00, 5, 'assets/img/rtx-4070-super.svg', 1),

(3, 'Corsair Vengeance DDR5 32GB (2x16) 6000MHz', 'corsair-vengeance-ddr5-32gb', 'Corsair',
 'Kit dual channel DDR5 6000 MHz CL30, perfil EXPO/XMP 3.0 y disipador de aluminio de bajo perfil.',
 189500.50, 24, 'assets/img/corsair-ddr5.svg', 0),

(4, 'Samsung 990 PRO 2TB NVMe Gen4', 'samsung-990-pro-2tb', 'Samsung',
 'SSD M.2 2280 PCIe 4.0 x4 con hasta 7450 MB/s de lectura secuencial y controlador Pascal.',
 265000.00, 18, 'assets/img/samsung-990-pro.svg', 1),

(5, 'Teclado Mecánico Logitech G Pro X TKL', 'logitech-g-pro-x-tkl', 'Logitech',
 'Teclado mecánico tenkeyless inalámbrico LIGHTSPEED, switches GX intercambiables e iluminación RGB LIGHTSYNC.',
 154900.00, 30, 'assets/img/logitech-gprox.svg', 0),

(5, 'Monitor Gamer LG UltraGear 27" 165Hz', 'lg-ultragear-27-165hz', 'LG',
 'Panel IPS QHD 2560x1440, 165 Hz, 1 ms GtG, HDR10 y compatibilidad con NVIDIA G-SYNC.',
 398000.00, 8, 'assets/img/lg-ultragear.svg', 0),

(6, 'PC Armada Gamer Ryzen 5 + RTX 4060', 'pc-gamer-ryzen5-rtx4060', 'HardStore',
 'Equipo completo: Ryzen 5 7600, 16 GB DDR5, SSD 1 TB NVMe, RTX 4060 8 GB, fuente 650W 80+ Bronze y gabinete con 4 fans ARGB.',
 1899000.00, 3, 'assets/img/pc-gamer.svg', 1),

(1, 'Intel Core i5-14600KF', 'intel-core-i5-14600kf', 'Intel',
 '14 núcleos (6P + 8E) / 20 hilos, hasta 5.3 GHz. Excelente relación precio-rendimiento en LGA1700.',
 372000.00, 0, 'assets/img/intel-i5-14600kf.svg', 0);

-- Usuario administrador de ejemplo.
-- Contraseña en claro: Admin1234  (cambiala después de instalar)
INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES
('Administrador', 'admin@hardstore.test',
 '$2y$12$AX8MaRfcYiawy9kSMOsysuyW5jVwZQqTjQ2KboW0WDZdpDYw9X.C2', 'admin');
