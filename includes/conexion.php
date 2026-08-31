<?php
/**
 * includes/conexion.php
 * ---------------------------------------------------------------------------
 * Conexión reutilizable a MySQL mediante PDO (patrón singleton).
 *
 * Uso:
 *     require_once __DIR__ . '/conexion.php';
 *     $pdo = Conexion::obtener();
 *     $stmt = $pdo->prepare('SELECT * FROM productos WHERE id = :id');
 *     $stmt->execute([':id' => $id]);
 *
 * Decisiones de seguridad:
 *  · ERRMODE_EXCEPTION      -> los errores lanzan excepción, nunca pasan silenciosos.
 *  · EMULATE_PREPARES=false -> prepared statements REALES del lado del servidor,
 *                              lo que hace imposible la inyección SQL por concatenación.
 *  · FETCH_ASSOC            -> arrays asociativos limpios, sin índices duplicados.
 *  · charset utf8mb4 en el DSN -> evita el viejo truco de inyección vía charset.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

final class Conexion
{
    /** @var PDO|null Instancia única compartida durante el request */
    private static ?PDO $pdo = null;

    /** Clase estática: no se instancia ni se clona. */
    private function __construct() {}
    private function __clone() {}

    /**
     * Devuelve la conexión PDO, creándola la primera vez que se pide.
     *
     * @throws RuntimeException si la conexión falla.
     */
    public static function obtener(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
            PDO::ATTR_PERSISTENT         => false,
        ];

        try {
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);
        } catch (PDOException $e) {
            // Nunca exponemos el mensaje real de la BD al usuario final:
            // podría revelar host, usuario o estructura interna.
            error_log('[DB] Fallo de conexión: ' . $e->getMessage());

            $detalle = (APP_ENV === 'dev') ? ' Detalle: ' . $e->getMessage() : '';
            throw new RuntimeException('No se pudo conectar a la base de datos.' . $detalle, 0, $e);
        }

        return self::$pdo;
    }
}
