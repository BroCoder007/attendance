import { fail, redirect } from '@sveltejs/kit';
import { authenticate, startLogin } from '#lib/server/store.js';
export function load({ locals }) {
  if (locals.user)
    redirect(303, locals.user.role === 'teacher' ? '/desk' : '/student');
}
export const actions = {
  default: async ({ request, cookies, getClientAddress, url }) => {
    const data = await request.formData();
    let user;
    try {
      user = authenticate(
        data.get('login'),
        data.get('password'),
        getClientAddress()
      );
    } catch (error) {
      return fail(400, { error: error.message });
    }
    cookies.set('rollcall_session', startLogin(user.id), {
      path: '/',
      httpOnly: true,
      sameSite: 'lax',
      secure: url.protocol === 'https:',
      maxAge: 7 * 86400
    });
    redirect(303, user.role === 'teacher' ? '/desk' : '/student');
  }
};
