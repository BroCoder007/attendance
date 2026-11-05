import { fail } from '@sveltejs/kit';
import QRCode from 'qrcode';
import { requireUser } from '#lib/server/access.js';
import {
  db,
  now,
  signQr,
  recordAttendance,
  rateLimit
} from '#lib/server/store.js';
export async function load({ locals }) {
  const user = requireUser(locals, 'student');
  const qrExpires = now() + 120_000;
  const qr = signQr(user.id, qrExpires);
  return {
    rooms: db()
      .prepare(
        'SELECT r.id,r.subject,r.ends,r.cidr,(SELECT marked_at FROM attendance WHERE room_id=r.id AND student_id=?) AS marked_at FROM rooms r WHERE teacher_id=? AND closed=0 AND starts<=? AND ends>? ORDER BY starts DESC'
      )
      .all(user.id, user.owner_id, now(), now()),
    history: db()
      .prepare(
        'SELECT a.marked_at,a.method,r.subject FROM attendance a JOIN rooms r ON r.id=a.room_id WHERE student_id=? ORDER BY marked_at DESC LIMIT 100'
      )
      .all(user.id),
    qr,
    qrImage: await QRCode.toDataURL(qr, {
      width: 240,
      margin: 2,
      color: { dark: '#142b3b', light: '#ffffff' }
    }),
    qrExpires
  };
}
export const actions = {
  checkin: async ({ locals, request, getClientAddress }) => {
    const user = requireUser(locals, 'student');
    const data = await request.formData();
    try {
      rateLimit(`code:${user.id}`, 15);
      const added = recordAttendance({
        roomId: Number(data.get('room')),
        studentId: user.id,
        ip: getClientAddress(),
        code: String(data.get('code') || '').trim()
      });
      return {
        message: added
          ? 'You are checked in. Have a good class.'
          : 'You are already checked in.'
      };
    } catch (error) {
      return fail(400, { error: error.message });
    }
  }
};
