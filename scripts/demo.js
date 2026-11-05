// Explicitly isolated synthetic data for local previews. Never used by the app itself.
import { existsSync } from 'node:fs';
if (process.env.NODE_ENV === 'production')
  throw new Error('Demo setup is disabled in production.');
process.env.DB_PATH = 'data/demo.sqlite';
if (existsSync(process.env.DB_PATH))
  throw new Error(
    'data/demo.sqlite already exists. Reuse it; demo setup never overwrites a database.'
  );
const { createUser, createRoom, recordAttendance } =
  await import('../src/lib/server/store.js');
const teacher = createUser({
  role: 'teacher',
  name: 'Ananya Rao',
  login: 'staff@example.test',
  password: 'Classroom-demo-2026'
});
const names = [
  'Aditi Sharma',
  'Arjun Patel',
  'Diya Nair',
  'Ishaan Mehta',
  'Kavya Rao',
  'Neel Joshi',
  'Riya Das',
  'Vihaan Shah'
];
const students = names.map((name, i) =>
  createUser({
    role: 'student',
    name,
    login: `cs${String(i + 1).padStart(3, '0')}`,
    password: 'Student-demo-2026',
    ownerId: teacher
  })
);
const room = createRoom(teacher, 'Data structures · Section A', 60);
for (const id of students.slice(0, 5))
  recordAttendance({
    roomId: room,
    studentId: id,
    teacherId: teacher,
    ip: '127.0.0.1',
    method: 'staff'
  });
createRoom(teacher, 'Database systems · Lab 02', 30);
console.log(
  'Synthetic preview database created: data/demo.sqlite. See docs/DEMO.md for local-only accounts.'
);
