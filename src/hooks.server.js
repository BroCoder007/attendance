import { resolveLogin } from '#lib/server/store.js';
export async function handle({ event, resolve }) {
  event.locals.user = resolveLogin(event.cookies.get('rollcall_session'));
  const response = await resolve(event);
  response.headers.set('X-Content-Type-Options', 'nosniff');
  response.headers.set('Referrer-Policy', 'same-origin');
  response.headers.set('X-Frame-Options', 'DENY');
  response.headers.set(
    'Permissions-Policy',
    'camera=(self), microphone=(), geolocation=()'
  );
  response.headers.set('Cache-Control', 'private, no-store');
  return response;
}
