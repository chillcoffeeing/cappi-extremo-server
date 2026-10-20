# Autenticación

## Modelo

La API es stateless. Laravel Sanctum se usa en modo token-only: no hay sesión web, cookie de refresh ni protección CSRF para las rutas API.

- Access token: Sanctum, almacenado hasheado en `personal_access_tokens`, TTL de 15 minutos.
- Refresh token: valor opaco almacenado hasheado en `refresh_tokens`, 24 horas sin `remember` y 30 días con `remember`.
- Ambos tokens se devuelven en el JSON de login/refresh.
- El refresh es rotativo: consumirlo lo revoca y emite un par nuevo. Reutilizarlo devuelve `401`.
- Logout revoca el access token actual y el refresh asociado.

El frontend conserva el access token en memoria y nunca debe guardarlo en `localStorage`.

## Endpoints

- `POST /api/auth/login`: credenciales y `remember`; devuelve usuario, `accessToken`, `refreshToken` y `expiresIn: 900000`.
- `POST /api/auth/register`: crea representante y draft de onboarding.
- `POST /api/auth/refresh`: recibe `refreshToken` y devuelve un par nuevo.
- `POST /api/auth/logout`: requiere Bearer token y responde `204`.
- `POST /api/auth/forgot-password`: respuesta neutra `204` para no revelar si existe el email.
  Genera el token de reset (1 h), pero **no se envía**: no hay correo saliente todavía
  (`docs/notificaciones/`, F-038). Hoy el reset solo funciona si el equipo hace llegar el enlace.
- `POST /api/auth/reset-password`: consume token de recuperación (transacción con lock), guarda la contraseña hasheada, invalida los demás enlaces de reset y cierra todas las sesiones del usuario (F-056).
- `POST /api/auth/verify-email`: consume un token de verificación. Hoy ningún flujo emite esos
  tokens (llegan con F-038), así que `email_verified_at` queda vacío y nada depende de él.

## Implementación Laravel

- `User` debe usar `Laravel\Sanctum\HasApiTokens`.
- Proteger las rutas privadas con `auth:sanctum`.
- Validar credenciales y payloads con Form Requests.
- Nunca incluir hashes, tokens almacenados ni datos internos en Resources.
- Las respuestas de autenticación deben conservar exactamente los nombres del contrato TypeScript.
- Rate limiting en todas las rutas públicas de `/api/auth/*` (limiters `portal-*` en `AppServiceProvider`; 429 con `{message}`). Límites y nota de proxies en `api-contract.md` → "Seguridad de cuentas (F-056)".
- Nunca escribir tokens de reset/verificación en el log.

## Onboarding

Registrar no concede acceso al portal. El estado puede ser `INCOMPLETO`, `LISTO_PARA_CONFIRMAR` o `COMPLETADO`. El frontend redirige según el estado confirmado por el servidor; no se debe inferir la finalización desde estado local.
