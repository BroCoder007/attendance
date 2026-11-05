import { redirect } from '@sveltejs/kit';
import { endLogin } from '#lib/server/store.js';
export function POST({ cookies }) {
  endLogin(cookies.get('rollcall_session'));
  cookies.delete('rollcall_session', { path: '/' });
  redirect(303, '/');
}
