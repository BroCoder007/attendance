import { fail } from '@sveltejs/kit';
import { requireRoom } from '#lib/server/access.js';
import {
  db,
  roster,
  rotateCode,
  recordAttendance,
  readQr,
  rateLimit
} from '#lib/server/store.js';
export function load({ locals, params }) {
  const room = requireRoom(locals, params.id);
  return {
    room,
    students: roster(locals.user.id),
    records: db()
      .prepare(
        'SELECT a.*,u.name,u.login FROM attendance a JOIN users u ON u.id=a.student_id WHERE room_id=? ORDER BY marked_at DESC'
      )
      .all(room.id)
  };
}
export const actions = {
  rotate: ({ locals, params }) => {
    const room = requireRoom(locals, params.id);
    try {
      rotateCode(room.id, locals.user.id);
    } catch (error) {
      return fail(400, { error: error.message });
    }
    return { message: 'New check-in code is ready.' };
  },
  close: ({ locals, params }) => {
    const room = requireRoom(locals, params.id);
    db()
      .prepare('UPDATE rooms SET closed=1 WHERE id=? AND teacher_id=?')
      .run(room.id, locals.user.id);
    return { message: 'Attendance window closed. Your records are saved.' };
  },
  mark: async ({ locals, params, request, getClientAddress }) => {
    const room = requireRoom(locals, params.id);
    const data = await request.formData();
    try {
      rateLimit(`staff:${locals.user.id}`, 120, 60_000);
      const qr = String(data.get('qr') || '').trim();
      const added = recordAttendance({
        roomId: room.id,
        studentId: qr ? readQr(qr) : Number(data.get('student')),
        teacherId: locals.user.id,
        method: qr ? 'qr' : 'staff',
        ip: getClientAddress()
      });
      return {
        message: added
          ? 'Student marked present.'
          : 'Already present. No duplicate was added.'
      };
    } catch (error) {
      return fail(400, { error: error.message });
    }
  }
};
