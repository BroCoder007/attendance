import test from 'node:test';
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { mkdtempSync, rmSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve, sep } from 'node:path';
import { createServer } from 'node:net';
import { once } from 'node:events';

test(
  'real server: authentication, scoped actions, check-in, QR, CSV and CSRF',
  { timeout: 30000 },
  async () => {
    const folder = mkdtempSync(join(tmpdir(), 'rollcall-http-'));
    process.env.DB_PATH = join(folder, 'test.sqlite');
    process.env.AUTH_SECRET = 'isolated-http-test-key-never-use-in-production';
    const s = await import('../src/lib/server/store.js');
    const teacher = s.createUser({
      role: 'teacher',
      name: 'Test Staff',
      login: 'staff@example.test',
      password: 'staff-test-password'
    });
    s.createUser({
      role: 'teacher',
      name: 'Other Staff',
      login: 'other@example.test',
      password: 'other-test-password'
    });
    const student = s.createUser({
      role: 'student',
      name: 'Test Student',
      login: 'cs001',
      password: 'student-test-password',
      ownerId: teacher
    });
    const second = s.createUser({
      role: 'student',
      name: 'Second Student',
      login: 'cs002',
      password: 'second-test-password',
      ownerId: teacher
    });
    const portServer = createServer().listen(0, '127.0.0.1');
    await once(portServer, 'listening');
    const port = portServer.address().port;
    await new Promise((done) => portServer.close(done));
    const origin = `http://127.0.0.1:${port}`;
    // Canonical origin is a build-time SvelteKit 3 setting. The isolated server may bind a different local port.
    const originValue = readFileSync('build/adapter-node.js', 'utf8').match(
      /export const origin = (.*);/
    )[1];
    const canonicalOrigin =
      originValue === 'undefined'
        ? origin.replace('http:', 'https:')
        : JSON.parse(originValue);
    const child = spawn(process.execPath, ['build/index.js'], {
      env: {
        ...process.env,
        HOST: '127.0.0.1',
        PORT: String(port),
        ORIGIN: origin,
        NODE_ENV: 'production'
      },
      windowsHide: true,
      stdio: ['ignore', 'pipe', 'pipe']
    });
    let logs = '';
    child.stdout.on('data', (x) => (logs += x));
    child.stderr.on('data', (x) => (logs += x));
    const request = (path, cookie = '', values, source = canonicalOrigin) =>
      fetch(origin + path, {
        method: values ? 'POST' : 'GET',
        redirect: 'manual',
        headers: {
          cookie,
          accept: 'text/html',
          ...(values
            ? {
                origin: source,
                'content-type': 'application/x-www-form-urlencoded'
              }
            : {})
        },
        body: values ? new URLSearchParams(values) : undefined
      });
    async function login(user, password) {
      const response = await request('/', '', { login: user, password });
      assert.equal(response.status, 303);
      const cookie = response.headers
        .getSetCookie()
        .find((c) => c.startsWith('rollcall_session='));
      assert.match(cookie, /HttpOnly/i);
      assert.match(cookie, /SameSite=Lax/i);
      return cookie.split(';')[0];
    }
    try {
      let ready = false;
      let last = '';
      for (let i = 0; i < 60; i++) {
        try {
          const r = await request('/');
          if (r.status === 200) {
            ready = true;
            break;
          }
          last = `${r.status}: ${await r.text()}`;
        } catch (error) {
          last = error.message;
        }
        await new Promise((r) => setTimeout(r, 100));
      }
      assert.ok(ready, logs + '\n' + last);
      assert.equal((await request('/desk')).status, 303);
      assert.equal(
        (
          await request('/', '', {
            login: 'staff@example.test',
            password: 'bad'
          })
        ).status,
        400
      );
      const staff = await login('staff@example.test', 'staff-test-password');
      const other = await login('other@example.test', 'other-test-password');
      const pupil = await login('cs001', 'student-test-password');
      assert.equal((await request('/desk', pupil)).status, 403);
      const created = await request('/desk?/create', staff, {
        subject: 'HTTP regression class',
        minutes: '10',
        cidr: ''
      });
      assert.equal(created.status, 303);
      const path = created.headers.get('location');
      assert.match(path, /^\/room\/\d+$/);
      const id = Number(path.split('/').pop());
      const room = s.ownRoom(id, teacher);
      assert.equal((await request(path, other)).status, 404);
      assert.equal((await request(`/export/${id}`, other)).status, 404);
      assert.equal((await request(`/export/${id}`, pupil)).status, 403);
      assert.equal(
        (await request(path + '?/mark', pupil, { student: String(student) }))
          .status,
        403
      );
      const denied = await request(
        '/student?/checkin',
        pupil,
        { room: String(id), code: room.code },
        'https://untrusted.example'
      );
      assert.equal(denied.status, 403);
      assert.ok(
        (await (await request('/student', pupil)).text()).includes(
          'pattern="[0-9]{6}"'
        ),
        'The browser must accept a six-digit check-in code.'
      );
      const checkin = await request('/student?/checkin', pupil, {
        room: String(id),
        code: room.code
      });
      assert.equal(checkin.status, 200);
      assert.equal(
        (
          await request('/student?/checkin', pupil, {
            room: String(id),
            code: room.code
          })
        ).status,
        200
      );
      assert.equal(
        s
          .db()
          .prepare('SELECT count(*) AS n FROM attendance WHERE room_id=?')
          .get(id).n,
        1
      );
      assert.equal(
        (await request(path + '?/mark', staff, { qr: s.signQr(second) }))
          .status,
        200
      );
      const csv = await request(`/export/${id}`, staff);
      assert.equal(csv.status, 200);
      assert.match(csv.headers.get('content-type'), /text\/csv/);
      assert.match(await csv.text(), /Test Student.*Present/);
      const studentPage = await request('/student', pupil);
      const html = await studentPage.text();
      assert.equal(studentPage.status, 200);
      assert.ok(!html.includes('Second Student'));
      assert.equal(
        studentPage.headers.get('cache-control'),
        'private, no-store'
      );
      assert.match(
        studentPage.headers.get('content-security-policy'),
        /frame-ancestors 'none'/
      );
      assert.equal((await request(path + '?/close', staff, {})).status, 200);
      const closed = await request('/student?/checkin', pupil, {
        room: String(id),
        code: room.code
      });
      assert.equal(closed.status, 400);
      assert.equal((await request('/logout', staff, {})).status, 303);
      assert.equal((await request('/desk', staff)).status, 303);
    } finally {
      const closed = once(child, 'close');
      child.kill();
      await closed;
      s.db().close();
      const absolute = resolve(folder);
      if (
        absolute.startsWith(resolve(tmpdir()) + sep) &&
        absolute.split(sep).pop().startsWith('rollcall-http-')
      )
        rmSync(absolute, { recursive: true, force: true });
    }
  }
);
