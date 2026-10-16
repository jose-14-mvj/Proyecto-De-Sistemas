<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Envío de notificaciones Web Push sin librerías externas (solo OpenSSL de PHP).
 *
 * - VAPID (RFC 8292): el servidor firma un JWT ES256 con su clave privada P-256.
 * - Cifrado del mensaje (RFC 8291 / RFC 8188, "aes128gcm"): ECDH con la clave del
 *   navegador + HKDF + AES-128-GCM. Solo el navegador de la persona puede leerlo.
 *
 * Las claves VAPID se generan una vez con: php artisan migrasense:claves-push
 */
class WebPush
{
    /** Prefijo DER de una clave pública P-256 (SubjectPublicKeyInfo, punto sin comprimir) */
    private const SPKI_P256 = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    public function __construct(
        private string $clavePublica,    // base64url, 65 bytes (0x04 || x || y)
        private string $clavePrivada,    // base64url, 32 bytes (d)
        private string $sujeto,          // mailto:correo@… o https://…
    ) {
    }

    /** Orden de la curva P-256: la clave privada debe ser 1 ≤ d < n */
    private const N_P256 = 'ffffffff00000000ffffffffffffffffbce6faada7179e84f3b9cac2fc632551';

    /**
     * Nueva clave EC P-256.
     * No se usa openssl_pkey_new(): en Windows/XAMPP falla porque OpenSSL no encuentra
     * su openssl.cnf. En su lugar se toma un número aleatorio seguro como clave privada
     * y OpenSSL solo la lee (y calcula la pública), lo que no necesita configuración.
     */
    public static function nuevaClaveEc()
    {
        do {
            $d = random_bytes(32);
            $hex = bin2hex($d);
        } while ($hex === str_repeat('0', 64) || strcmp($hex, self::N_P256) >= 0);

        $der = hex2bin('30310201010420').$d.hex2bin('a00a06082a8648ce3d030107');
        $k = openssl_pkey_get_private("-----BEGIN EC PRIVATE KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END EC PRIVATE KEY-----\n");
        if (! $k) {
            throw new RuntimeException('OpenSSL no pudo leer la clave EC: '.(string) openssl_error_string());
        }
        return $k;
    }

    /** Par de claves VAPID nuevo: ['publica' => b64url, 'privada' => b64url] */
    public static function generarClaves(): array
    {
        $k = self::nuevaClaveEc();
        $ec = openssl_pkey_get_details($k)['ec'];
        return [
            'publica' => self::b64u("\x04".self::pad32($ec['x']).self::pad32($ec['y'])),
            'privada' => self::b64u(self::pad32($ec['d'])),
        ];
    }

    /**
     * Envía un mensaje a una suscripción del navegador.
     * $sub = ['endpoint' => …, 'p256dh' => b64url, 'auth' => b64url]
     * Devuelve el código HTTP del servicio de push (201 = aceptado; 404/410 = la suscripción ya no existe).
     */
    public function enviar(array $sub, string $mensaje, int $ttl = 86400, string $urgencia = 'normal'): int
    {
        $cuerpo = self::cifrar($mensaje, $sub['p256dh'], $sub['auth']);
        $r = Http::withHeaders([
                'Authorization'    => 'vapid t='.$this->jwt($sub['endpoint']).', k='.$this->clavePublica,
                'Content-Encoding' => 'aes128gcm',
                'TTL'              => (string) $ttl,
                'Urgency'          => $urgencia,
            ])
            ->withBody($cuerpo, 'application/octet-stream')
            ->timeout(15)
            ->post($sub['endpoint']);
        return $r->status();
    }

    /** JWT de VAPID para el servicio de push del endpoint (válido 12 h) */
    public function jwt(string $endpoint, ?int $ahora = null): string
    {
        $u = parse_url($endpoint);
        if (empty($u['scheme']) || empty($u['host']) || $u['scheme'] !== 'https') {
            throw new RuntimeException('Endpoint de push no válido.');
        }
        $aud = $u['scheme'].'://'.$u['host'].(isset($u['port']) ? ':'.$u['port'] : '');
        $cab = self::b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $dat = self::b64u(json_encode(['aud' => $aud, 'exp' => ($ahora ?? time()) + 12 * 3600, 'sub' => $this->sujeto], JSON_UNESCAPED_SLASHES));
        $firmar = $cab.'.'.$dat;
        if (! openssl_sign($firmar, $der, $this->clavePrivadaPem(), OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('No se pudo firmar el JWT de VAPID.');
        }
        return $firmar.'.'.self::b64u(self::derARaw($der));
    }

    /**
     * Cifra el mensaje para el navegador (aes128gcm, un solo registro).
     * $local (opcional, para pruebas) = ['privada' => recurso/clave, 'publica' => 65 bytes] y $salt de 16 bytes.
     */
    public static function cifrar(string $mensaje, string $p256dhB64, string $authB64, ?array $local = null, ?string $salt = null): string
    {
        $uaPublica = self::db64u($p256dhB64);
        $auth = self::db64u($authB64);
        if (strlen($uaPublica) !== 65 || $uaPublica[0] !== "\x04" || strlen($auth) !== 16) {
            throw new RuntimeException('Claves de suscripción no válidas.');
        }
        if (strlen($mensaje) > 3800) {
            throw new RuntimeException('Mensaje de push demasiado largo.');
        }

        // Par efímero del servidor
        if (! $local) {
            $k = self::nuevaClaveEc();
            $ec = openssl_pkey_get_details($k)['ec'];
            $local = ['privada' => $k, 'publica' => "\x04".self::pad32($ec['x']).self::pad32($ec['y'])];
        }
        $pemUa = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode(hex2bin(self::SPKI_P256).$uaPublica), 64, "\n")."-----END PUBLIC KEY-----\n";
        $secretoEcdh = openssl_pkey_derive(openssl_pkey_get_public($pemUa), $local['privada'], 32);
        if ($secretoEcdh === false) {
            throw new RuntimeException('Falló el acuerdo de claves ECDH.');
        }

        // RFC 8291 §3.4: IKM a partir del secreto de autenticación
        $ikm = hash_hkdf('sha256', $secretoEcdh, 32, "WebPush: info\x00".$uaPublica.$local['publica'], $auth);
        // RFC 8188: clave y nonce del contenido
        $salt ??= random_bytes(16);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $salt);

        $cifrado = openssl_encrypt($mensaje."\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cifrado === false) {
            throw new RuntimeException('Falló el cifrado AES-GCM.');
        }
        // Cabecera: salt(16) | tamaño de registro (4096) | largo de keyid (65) | clave pública del servidor
        return $salt.pack('N', 4096).chr(65).$local['publica'].$cifrado.$tag;
    }

    /* ---------------- utilidades ---------------- */

    private function clavePrivadaPem()
    {
        $d = self::db64u($this->clavePrivada);
        $pub = self::db64u($this->clavePublica);
        if (strlen($d) !== 32 || strlen($pub) !== 65) {
            throw new RuntimeException('Claves VAPID mal configuradas (VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY).');
        }
        // ECPrivateKey (SEC1) con la curva P-256 y la clave pública
        $der = hex2bin('30770201010420').$d.hex2bin('a00a06082a8648ce3d030107a144034200').$pub;
        $pem = "-----BEGIN EC PRIVATE KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END EC PRIVATE KEY-----\n";
        $k = openssl_pkey_get_private($pem);
        if (! $k) {
            throw new RuntimeException('OpenSSL no aceptó la clave privada VAPID.');
        }
        return $k;
    }

    /** Firma ECDSA DER → r||s de 64 bytes (formato JWS) */
    private static function derARaw(string $der): string
    {
        $pos = 2;
        if (ord($der[1]) & 0x80) {
            $pos += ord($der[1]) & 0x7f;
        }
        $leer = function () use ($der, &$pos) {
            $pos++;                                   // 0x02 INTEGER
            $len = ord($der[$pos++]);
            $v = substr($der, $pos, $len);
            $pos += $len;
            return str_pad(ltrim($v, "\x00"), 32, "\x00", STR_PAD_LEFT);
        };
        return $leer().$leer();
    }

    private static function pad32(string $b): string
    {
        return str_pad($b, 32, "\x00", STR_PAD_LEFT);
    }

    public static function b64u(string $b): string
    {
        return rtrim(strtr(base64_encode($b), '+/', '-_'), '=');
    }

    public static function db64u(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/').str_repeat('=', (4 - strlen($s) % 4) % 4), true);
    }
}
