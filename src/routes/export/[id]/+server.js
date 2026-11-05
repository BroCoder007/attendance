import { requireRoom } from '#lib/server/access.js';
import { db, csvCell } from '#lib/server/store.js';
export function GET({ locals, params }) {
  const room = requireRoom(locals, params.id);
  const rows = db()
    .prepare(
      "SELECT u.login,u.name,a.marked_at,a.method FROM users u LEFT JOIN attendance a ON a.student_id=u.id AND a.room_id=? WHERE u.owner_id=? AND u.role='student' AND (u.active=1 OR a.student_id IS NOT NULL) ORDER BY u.name COLLATE NOCASE"
    )
    .all(room.id, locals.user.id);
  const csv = [
    ['Roll number', 'Name', 'Class', 'Status', 'Checked in (UTC)', 'Method'],
    ...rows.map((r) => [
      r.login,
      r.name,
      room.subject,
      r.marked_at ? 'Present' : 'Not recorded',
      r.marked_at ? new Date(r.marked_at).toISOString() : '',
      r.method || ''
    ])
  ]
    .map((row) => row.map(csvCell).join(','))
    .join('\r\n');
  return new Response('\ufeff' + csv, {
    headers: {
      'Content-Type': 'text/csv; charset=utf-8',
      'Content-Disposition': `attachment; filename="rollcall-${room.id}.csv"`
    }
  });
}
