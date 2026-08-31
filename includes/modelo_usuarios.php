<?php
/**
 * includes/modelo_usuarios.php
 * ---------------------------------------------------------------------------
 * Registro y autenticación de usuarios.
 *
 * Reglas de seguridad aplicadas:
 *  · password_hash() con el algoritmo por defecto de PHP (bcrypt/argon2).
 *  · password_verify() para comparar: nunca `==` sobre hashes.
 *  · Se ejecuta password_verify incluso si el email no existe, para que el
 *    tiempo de respuesta no revele si el mail está registrado.
 *  · session_regenerate_id() al loguear -> previene session fixation.
 */

declare(strict_types=1);

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/funciones.php';

final class UsuarioRepo
{
    /** Hash "señuelo" válido para igualar tiempos cuando el email no existe. */
    private const HASH_DUMMY = '$2y$12$AX8MaRfcYiawy9kSMOsysuyW5jVwZQqTjQ2KboW0WDZdpDYw9X.C2';

    /**
     * Crea un usuario nuevo.
     *
     * @return array{ok:bool, error?:string, usuario?:array}
     */
    public static function registrar(
        string $nombre,
        string $email,
        string $password,
        ?string $telefono = null,
        ?string $direccion = null
    ): array {
        $nombre = trim($nombre);
        $email  = strtolower(trim($email));

        // --- Validación del lado del servidor (la del cliente no cuenta) ---
        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 100) {
            return ['ok' => false, 'error' => 'El nombre debe tener entre 2 y 100 caracteres.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
            return ['ok' => false, 'error' => 'El email no es válido.'];
        }
        if (mb_strlen($password) < 8) {
            return ['ok' => false, 'error' => 'La contraseña debe tener al menos 8 caracteres.'];
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            return ['ok' => false, 'error' => 'La contraseña debe combinar letras y números.'];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = Conexion::obtener()->prepare(
                'INSERT INTO usuarios (nombre, email, password_hash, telefono, direccion)
                 VALUES (:nombre, :email, :hash, :telefono, :direccion)'
            );
            $stmt->execute([
                ':nombre'    => $nombre,
                ':email'     => $email,
                ':hash'      => $hash,
                ':telefono'  => $telefono !== null && $telefono !== '' ? trim($telefono) : null,
                ':direccion' => $direccion !== null && $direccion !== '' ? trim($direccion) : null,
            ]);
        } catch (PDOException $e) {
            // 23000 = violación de restricción (acá: email duplicado por UNIQUE KEY)
            if ($e->getCode() === '23000') {
                return ['ok' => false, 'error' => 'Ese email ya está registrado.'];
            }
            error_log('[usuarios.registrar] ' . $e->getMessage());
            return ['ok' => false, 'error' => 'No se pudo crear la cuenta. Intentá de nuevo.'];
        }

        $id = (int) Conexion::obtener()->lastInsertId();

        return ['ok' => true, 'usuario' => [
            'id'     => $id,
            'nombre' => $nombre,
            'email'  => $email,
            'rol'    => 'cliente',
        ]];
    }

    /**
     * Verifica credenciales y, si son correctas, abre la sesión.
     *
     * @return array{ok:bool, error?:string, usuario?:array}
     */
    public static function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));

        $stmt = Conexion::obtener()->prepare(
            'SELECT id, nombre, email, password_hash, telefono, direccion, rol, activo
             FROM usuarios WHERE email = :email LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $u = $stmt->fetch();

        // Mensaje genérico a propósito: no delatamos si falló el mail o la clave.
        $generico = ['ok' => false, 'error' => 'Email o contraseña incorrectos.'];

        if (!$u) {
            password_verify($password, self::HASH_DUMMY); // igualar tiempos
            return $generico;
        }
        if (!password_verify($password, $u['password_hash'])) {
            return $generico;
        }
        if (!(int) $u['activo']) {
            return ['ok' => false, 'error' => 'Tu cuenta está deshabilitada.'];
        }

        // Rehash transparente si PHP cambió el algoritmo/costo por defecto.
        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            $nuevo = password_hash($password, PASSWORD_DEFAULT);
            $up = Conexion::obtener()->prepare('UPDATE usuarios SET password_hash = :h WHERE id = :id');
            $up->execute([':h' => $nuevo, ':id' => $u['id']]);
        }

        $publico = [
            'id'        => (int) $u['id'],
            'nombre'    => $u['nombre'],
            'email'     => $u['email'],
            'telefono'  => $u['telefono'],
            'direccion' => $u['direccion'],
            'rol'       => $u['rol'],
        ];

        sesion_iniciar();
        session_regenerate_id(true);          // anti session fixation
        $_SESSION['usuario'] = $publico;

        return ['ok' => true, 'usuario' => $publico];
    }

    /** Cierra la sesión y destruye la cookie. */
    public static function logout(): void
    {
        sesion_iniciar();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'domain'   => $p['domain'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }
}
