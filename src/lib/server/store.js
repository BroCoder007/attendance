import { DatabaseSync } from 'node:sqlite';
import { mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import {
  randomBytes,
  randomInt,
  scryptSync,
  timingSafeEqual,
  createHash,
  createHmac
} from 'node:crypto';
import ipaddr from 'ipaddr.js';

let database;
export function db() {
  if (database) return database;
  const path = process.env.DB_PATH || 'data/rollcall.sqlite';
  if (path !== ':memory:')
    mkdirSync(dirname(resolve(path)), { recursive: true });
  database = new DatabaseSync(path);
  database.exec(`PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;
    CREATE TABLE IF NOT EXISTS users (
      id INTEGER PRIMARY KEY, role TEXT NOT NULL CHECK(role IN ('teacher','student')),
      name TEXT NOT NULL, login TEXT NOT NULL UNIQUE COLLATE NOCASE, password_hash TEXT NOT NULL,
      owner_id INTEGER REFERENCES users(id), active INTEGER NOT NULL DEFAULT 1, created_at INTEGER NOT NULL);
    CREATE TABLE IF NOT EXISTS logins (token_hash TEXT PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id), expires INTEGER NOT NULL);
    CREATE TABLE IF NOT EXISTS rooms (
      id INTEGER PRIMARY KEY, teacher_id INTEGER NOT NULL REFERENCES users(id), subject TEXT NOT NULL,
      cidr TEXT NOT NULL DEFAULT '', code TEXT NOT NULL, code_expires INTEGER NOT NULL,
      starts INTEGER NOT NULL, ends INTEGER NOT NULL, closed INTEGER NOT NULL DEFAULT 0);
    CREATE TABLE IF NOT EXISTS attendance (
      room_id INTEGER NOT NULL REFERENCES rooms(id), student_id INTEGER NOT NULL REFERENCES users(id),
      marked_at INTEGER NOT NULL, ip TEXT NOT NULL, method TEXT NOT NULL CHECK(method IN ('code','staff','qr')),
      PRIMARY KEY(room_id,student_id));
    CREATE TABLE IF NOT EXISTS attempts (bucket TEXT NOT NULL, at INTEGER NOT NULL);
    CREATE INDEX IF NOT EXISTS attempt_lookup ON attempts(bucket,at);
    CREATE INDEX IF NOT EXISTS room_owner ON rooms(teacher_id,starts);
    CREATE INDEX IF NOT EXISTS attendance_student ON attendance(student_id,marked_at);
    CREATE INDEX IF NOT EXISTS roster_owner ON users(owner_id,active);`);
  return database;
}

export const now = () => Date.now();
const hash = (value) => createHash('sha256').update(value).digest('hex');
export function passwordHash(password) {
  const salt = randomBytes(16).toString('hex');
  return `${salt}:${scryptSync(password, salt, 64).toString('hex')}`;
}
export function passwordMatches(password, encoded) {
  const [salt, key] = encoded.split(':');
  return timingSafeEqual(
    scryptSync(password, salt, 64),
    Buffer.from(key, 'hex')
  );
}
const dummyPassword = passwordHash(randomBytes(20).toString('hex'));
export function createUser({ role, name, login, password, ownerId = null }) {
  name = String(name || '').trim();
  login = String(login || '')
    .trim()
    .toLowerCase();
  if (
    !['teacher', 'student'].includes(role) ||
    !name ||
    name.length > 100 ||
    !/^[a-z0-9@._+\-]{2,100}$/.test(login)
  )
    throw new Error(
      'Use a name and a valid email or roll number (2–100 characters).'
    );
  if (
    typeof password !== 'string' ||
    password.length < 12 ||
    password.length > 128
  )
    throw new Error('Passwords must contain 12–128 characters.');
  if (
    role === 'student' &&
    !db()
      .prepare(
        "SELECT id FROM users WHERE id=? AND role='teacher' AND active=1"
      )
      .get(ownerId)
  )
    throw new Error('A student must belong to an active teacher.');
  if (db().prepare('SELECT id FROM users WHERE login=?').get(login))
    throw new Error('That email or roll number is already registered.');
  return Number(
    db()
      .prepare(
        'INSERT INTO users(role,name,login,password_hash,owner_id,created_at) VALUES(?,?,?,?,?,?)'
      )
      .run(role, name, login, passwordHash(password), ownerId, now())
      .lastInsertRowid
  );
}
export function rateLimit(bucket, limit = 8, interval = 15 * 60_000) {
  const time = now();
  db()
    .prepare('DELETE FROM attempts WHERE at < ?')
    .run(time - 24 * 60 * 60_000);
  const { count } = db()
    .prepare('SELECT count(*) AS count FROM attempts WHERE bucket=? AND at>?')
    .get(bucket, time - interval);
  if (count >= limit)
    throw new Error('Too many attempts. Please wait and try again.');
  db().prepare('INSERT INTO attempts(bucket,at) VALUES(?,?)').run(bucket, time);
}
export function authenticate(login, password, ip) {
  login = String(login).trim().toLowerCase();
  rateLimit(`ip:${ip}`, 40);
  rateLimit(`login:${hash(login)}`, 8);
  const user = db()
    .prepare('SELECT * FROM users WHERE login=? AND active=1')
    .get(login);
  const valid = passwordMatches(
    String(password).slice(0, 129),
    user?.password_hash || dummyPassword
  );
  if (!user || !valid)
    throw new Error('Email / roll number or password is incorrect.');
  db()
    .prepare('DELETE FROM attempts WHERE bucket=?')
    .run(`login:${hash(login)}`);
  return user;
}
export function startLogin(userId) {
  db().prepare('DELETE FROM logins WHERE expires<?').run(now());
  const token = randomBytes(32).toString('hex');
  db()
    .prepare('INSERT INTO logins VALUES(?,?,?)')
    .run(hash(token), userId, now() + 7 * 86400_000);
  return token;
}
export function resolveLogin(token) {
  if (!token || token.length !== 64) return null;
  return (
    db()
      .prepare(
        'SELECT u.id,u.role,u.name,u.login,u.owner_id FROM logins l JOIN users u ON u.id=l.user_id WHERE l.token_hash=? AND l.expires>? AND u.active=1'
      )
      .get(hash(token), now()) || null
  );
}
export function endLogin(token) {
  if (token)
    db().prepare('DELETE FROM logins WHERE token_hash=?').run(hash(token));
}
export function validCidr(cidr) {
  if (!cidr) return true;
  try {
    ipaddr.parseCIDR(cidr);
    return true;
  } catch {
    return false;
  }
}
export function matchesNetwork(ip, cidr) {
  if (!cidr) return true;
  try {
    const address = ipaddr.process(ip);
    let [network, bits] = ipaddr.parseCIDR(cidr);
    if (network.kind() === 'ipv6' && network.isIPv4MappedAddress()) {
      network = network.toIPv4Address();
      bits -= 96;
    }
    return address.kind() === network.kind() && address.match(network, bits);
  } catch {
    return false;
  }
}
export const isOpen = (room) =>
  Boolean(room && !room.closed && room.starts <= now() && room.ends > now());
export function ownRoom(id, teacherId) {
  return db()
    .prepare('SELECT * FROM rooms WHERE id=? AND teacher_id=?')
    .get(id, teacherId);
}
export function createRoom(teacherId, subject, minutes, cidr = '') {
  subject = String(subject || '').trim();
  minutes = Number(minutes);
  cidr = String(cidr || '').trim();
  if (!subject || subject.length > 120)
    throw new Error('Enter a class name of up to 120 characters.');
  if (!Number.isInteger(minutes) || minutes < 1 || minutes > 240)
    throw new Error('Choose a window of 1–240 minutes.');
  if (!validCidr(cidr))
    throw new Error('Enter a valid IPv4 or IPv6 network with a CIDR prefix.');
  const time = now();
  return Number(
    db()
      .prepare(
        'INSERT INTO rooms(teacher_id,subject,cidr,code,code_expires,starts,ends) VALUES(?,?,?,?,?,?,?)'
      )
      .run(
        teacherId,
        subject,
        cidr,
        String(randomInt(100000, 1000000)),
        time + 120_000,
        time,
        time + minutes * 60_000
      ).lastInsertRowid
  );
}
export function rotateCode(id, teacherId) {
  const room = ownRoom(id, teacherId);
  if (!isOpen(room)) throw new Error('This attendance window is closed.');
  db()
    .prepare(
      'UPDATE rooms SET code=?,code_expires=? WHERE id=? AND teacher_id=?'
    )
    .run(String(randomInt(100000, 1000000)), now() + 120_000, id, teacherId);
}
export function recordAttendance({
  roomId,
  studentId,
  teacherId,
  ip,
  code,
  method = 'code'
}) {
  const store = db();
  store.exec('BEGIN IMMEDIATE');
  try {
    const room = store.prepare('SELECT * FROM rooms WHERE id=?').get(roomId);
    const student = store
      .prepare("SELECT * FROM users WHERE id=? AND role='student' AND active=1")
      .get(studentId);
    if (
      !room ||
      !student ||
      student.owner_id !== room.teacher_id ||
      (method !== 'code' && teacherId !== room.teacher_id)
    )
      throw new Error('Class or student is not available to this account.');
    if (!isOpen(room)) throw new Error('This attendance window is closed.');
    if (method === 'code') {
      if (!matchesNetwork(ip, room.cidr))
        throw new Error('Connect to the classroom network to check in.');
      if (room.code_expires <= now() || code !== room.code)
        throw new Error(
          'That code is incorrect or expired. Ask your teacher for the current code.'
        );
    }
    const result = store
      .prepare(
        'INSERT INTO attendance(room_id,student_id,marked_at,ip,method) VALUES(?,?,?,?,?) ON CONFLICT(room_id,student_id) DO NOTHING'
      )
      .run(roomId, studentId, now(), ip, method);
    store.exec('COMMIT');
    return result.changes > 0;
  } catch (error) {
    store.exec('ROLLBACK');
    throw error;
  }
}
function secret() {
  if (!process.env.AUTH_SECRET || process.env.AUTH_SECRET.length < 32)
    throw new Error(
      'Set AUTH_SECRET to at least 32 random characters before starting Rollcall.'
    );
  return process.env.AUTH_SECRET;
}
export function signQr(userId, expires = now() + 120_000) {
  const body = Buffer.from(JSON.stringify({ id: userId, expires })).toString(
    'base64url'
  );
  return `${body}.${createHmac('sha256', secret()).update(body).digest('base64url')}`;
}
export function readQr(token) {
  if (typeof token !== 'string' || token.length > 500)
    throw new Error('Invalid student QR.');
  const [body, signature, extra] = token.split('.');
  const expected = createHmac('sha256', secret())
    .update(body || '')
    .digest();
  const supplied = Buffer.from(signature || '', 'base64url');
  if (
    extra ||
    supplied.length !== expected.length ||
    !timingSafeEqual(expected, supplied)
  )
    throw new Error('Invalid student QR.');
  let data;
  try {
    data = JSON.parse(Buffer.from(body, 'base64url').toString());
  } catch {
    throw new Error('Invalid student QR.');
  }
  if (
    !Number.isInteger(data.id) ||
    !Number.isFinite(data.expires) ||
    data.expires <= now() ||
    data.expires > now() + 121_000
  )
    throw new Error('Student QR has expired. Ask the student to refresh it.');
  return data.id;
}
export function csvCell(value) {
  let text = String(value ?? '');
  if (/^[\s]*[=+@\-]/.test(text) || /^[\t\r\n]/.test(text)) text = `'${text}`;
  return `"${text.replaceAll('"', '""')}"`;
}
export function roster(teacherId) {
  return db()
    .prepare(
      "SELECT id,name,login,active FROM users WHERE role='student' AND owner_id=? ORDER BY name COLLATE NOCASE"
    )
    .all(teacherId);
}
