import test from 'node:test';
import assert from 'node:assert/strict';
process.env.DB_PATH = ':memory:';
process.env.AUTH_SECRET = 'test-only-key-not-a-deployment-secret-123456789';
const s = await import('../src/lib/server/store.js');
const teacher = s.createUser({
  role: 'teacher',
  name: 'Teacher One',
  login: 'one@example.test',
  password: 'one-secure-password'
});
const other = s.createUser({
  role: 'teacher',
  name: 'Teacher Two',
  login: 'two@example.test',
  password: 'two-secure-password'
});
const student = s.createUser({
  role: 'student',
  name: 'Student One',
  login: 'cs001',
  password: 'student-password-1',
  ownerId: teacher
});
const foreign = s.createUser({
  role: 'student',
  name: 'Student Two',
  login: 'cs002',
  password: 'student-password-2',
  ownerId: other
});
const roomId = () => s.createRoom(teacher, 'Test class', 10, '192.168.2.0/24');

test('passwords are salted, verified and constrained', () => {
  const a = s.passwordHash('a strong password');
  const b = s.passwordHash('a strong password');
  assert.notEqual(a, b);
  assert.equal(s.passwordMatches('a strong password', a), true);
  assert.equal(s.passwordMatches('wrong', a), false);
  assert.throws(
    () =>
      s.createUser({
        role: 'teacher',
        name: 'X',
        login: 'xx',
        password: 'short'
      }),
    /12/
  );
});
test('authentication ignores login case, rejects unknown users and revokes sessions', () => {
  assert.equal(
    s.authenticate('ONE@example.test', 'one-secure-password', 'test-ip').id,
    teacher
  );
  assert.throws(
    () => s.authenticate('missing', 'wrong', 'test-ip'),
    /incorrect/
  );
  const token = s.startLogin(teacher);
  assert.equal(s.resolveLogin(token).id, teacher);
  assert.ok(
    !s.db().prepare('SELECT * FROM logins WHERE token_hash=?').get(token)
  );
  s.endLogin(token);
  assert.equal(s.resolveLogin(token), null);
});
test('login sessions expire and paused accounts cannot use existing sessions', () => {
  const token = s.startLogin(student);
  s.db().prepare('UPDATE users SET active=0 WHERE id=?').run(student);
  assert.equal(s.resolveLogin(token), null);
  s.db().prepare('UPDATE users SET active=1 WHERE id=?').run(student);
  s.db().prepare('UPDATE logins SET expires=0').run();
  assert.equal(s.resolveLogin(token), null);
});
test('rate limits stop repeated attempts', () => {
  s.rateLimit('test', 2);
  s.rateLimit('test', 2);
  assert.throws(() => s.rateLimit('test', 2), /Too many/);
});
test('IPv4, mapped IPv4 and IPv6 networks work without trusting forwarded headers', () => {
  assert.equal(s.matchesNetwork('192.168.2.7', '192.168.2.0/24'), true);
  assert.equal(s.matchesNetwork('::ffff:192.168.2.7', '192.168.2.0/24'), true);
  assert.equal(s.matchesNetwork('192.168.3.7', '192.168.2.0/24'), false);
  assert.equal(s.matchesNetwork('2001:db8::7', '2001:db8::/32'), true);
  assert.equal(s.matchesNetwork('::1', '192.168.2.0/24'), false);
  assert.equal(s.validCidr('bad'), false);
});
test('window validation and staff ownership', () => {
  assert.throws(() => s.createRoom(teacher, '', 10), /class name/);
  assert.throws(() => s.createRoom(teacher, 'Class', 999), /1–240/);
  assert.throws(() => s.createRoom(teacher, 'Class', 10, 'bad'), /CIDR/);
  const id = roomId();
  assert.equal(s.ownRoom(id, other), undefined);
  assert.throws(() => s.rotateCode(id, other), /closed/);
});
test('check-in enforces class, roster, network and current code; retries are idempotent', () => {
  const id = roomId();
  const room = s.ownRoom(id, teacher);
  const input = {
    roomId: id,
    studentId: student,
    ip: '192.168.2.7',
    code: room.code
  };
  assert.throws(
    () => s.recordAttendance({ ...input, studentId: foreign }),
    /not available/
  );
  assert.throws(
    () => s.recordAttendance({ ...input, ip: '10.0.0.1' }),
    /network/
  );
  assert.throws(
    () => s.recordAttendance({ ...input, code: 'bad' }),
    /incorrect or expired/
  );
  assert.equal(s.recordAttendance(input), true);
  assert.equal(s.recordAttendance(input), false);
  assert.equal(
    s
      .db()
      .prepare('SELECT count(*) AS n FROM attendance WHERE room_id=?')
      .get(id).n,
    1
  );
  assert.throws(
    () =>
      s
        .db()
        .prepare('INSERT INTO attendance VALUES(?,?,?,?,?)')
        .run(id, student, Date.now(), 'ip', 'code'),
    /UNIQUE/
  );
});
test('staff override requires owner and still respects closure and active account', () => {
  const id = roomId();
  const input = {
    roomId: id,
    studentId: student,
    teacherId: other,
    ip: '127.0.0.1',
    method: 'staff'
  };
  assert.throws(() => s.recordAttendance(input), /not available/);
  assert.equal(s.recordAttendance({ ...input, teacherId: teacher }), true);
  s.db().prepare('UPDATE rooms SET closed=1 WHERE id=?').run(id);
  assert.throws(
    () => s.recordAttendance({ ...input, teacherId: teacher }),
    /closed/
  );
  assert.throws(() => s.rotateCode(id, teacher), /closed/);
});
test('expired code and expired windows reject check-ins', () => {
  const id = roomId();
  const room = s.ownRoom(id, teacher);
  const input = {
    roomId: id,
    studentId: student,
    ip: '192.168.2.7',
    code: room.code
  };
  s.db().prepare('UPDATE rooms SET code_expires=0 WHERE id=?').run(id);
  assert.throws(() => s.recordAttendance(input), /expired/);
  s.rotateCode(id, teacher);
  assert.ok(s.ownRoom(id, teacher).code_expires > Date.now());
  s.db().prepare('UPDATE rooms SET ends=0 WHERE id=?').run(id);
  assert.throws(() => s.recordAttendance(input), /closed/);
});
test('signed QR rejects tampering, malformed tokens and expired passes', () => {
  const token = s.signQr(student);
  assert.equal(s.readQr(token), student);
  assert.throws(() => s.readQr(token + 'x'), /Invalid/);
  assert.throws(() => s.readQr('garbage'), /Invalid/);
  assert.throws(() => s.readQr(s.signQr(student, Date.now() - 1)), /expired/);
  assert.throws(
    () => s.readQr(s.signQr(student, Date.now() + 1000000)),
    /expired/
  );
});
test('CSV protects formulas and escapes quotes, commas and newlines', () => {
  assert.equal(s.csvCell('=SUM(1,2)'), '"\'=SUM(1,2)"');
  assert.equal(s.csvCell(' +cmd'), '"\' +cmd"');
  assert.equal(s.csvCell('A "quote",\nnext'), '"A ""quote"",\nnext"');
});
