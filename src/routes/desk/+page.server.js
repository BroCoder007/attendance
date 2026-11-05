import { fail, redirect } from '@sveltejs/kit';
import { requireUser } from '#lib/server/access.js';
import {
  db,
  createRoom,
  createUser,
  roster,
  passwordHash
} from '#lib/server/store.js';
export function load({ locals }) {
  const user = requireUser(locals, 'teacher');
  return {
    rooms: db()
      .prepare(
        'SELECT r.*, (SELECT count(*) FROM attendance a WHERE a.room_id=r.id) AS present FROM rooms r WHERE teacher_id=? ORDER BY starts DESC'
      )
      .all(user.id),
    students: roster(user.id)
  };
}
export const actions = {
  create: async ({ locals, request }) => {
    const user = requireUser(locals, 'teacher');
    const data = await request.formData();
    let id;
    try {
      id = createRoom(
        user.id,
        data.get('subject'),
        data.get('minutes'),
        data.get('cidr')
      );
    } catch (error) {
      return fail(400, { error: error.message, area: 'class' });
    }
    redirect(303, `/room/${id}`);
  },
  student: async ({ locals, request }) => {
    const user = requireUser(locals, 'teacher');
    const data = await request.formData();
    try {
      createUser({
        role: 'student',
        name: data.get('name'),
        login: data.get('login'),
        password: data.get('password'),
        ownerId: user.id
      });
    } catch (error) {
      return fail(400, { error: error.message, area: 'roster' });
    }
    return {
      message: 'Student added. Share their credentials privately.',
      area: 'roster'
    };
  },
  manage: async ({ locals, request }) => {
    const user = requireUser(locals, 'teacher');
    const data = await request.formData();
    const id = Number(data.get('id'));
    const student = db()
      .prepare(
        "SELECT id FROM users WHERE id=? AND owner_id=? AND role='student'"
      )
      .get(id, user.id);
    if (!student)
      return fail(404, { error: 'Student not found.', area: 'roster' });
    if (data.get('operation') === 'password') {
      const password = String(data.get('password') || '');
      if (password.length < 12 || password.length > 128)
        return fail(400, {
          error: 'Passwords must contain 12–128 characters.',
          area: 'roster'
        });
      db()
        .prepare('UPDATE users SET password_hash=? WHERE id=?')
        .run(passwordHash(password), id);
      db().prepare('DELETE FROM logins WHERE user_id=?').run(id);
    } else if (data.get('operation') === 'toggle') {
      db().prepare('UPDATE users SET active=1-active WHERE id=?').run(id);
      db().prepare('DELETE FROM logins WHERE user_id=?').run(id);
    } else return fail(400, { error: 'Unknown action.', area: 'roster' });
    return { message: 'Student account updated.', area: 'roster' };
  }
};
