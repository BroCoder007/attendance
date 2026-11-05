import { createUser, db, passwordHash } from '../src/lib/server/store.js';

async function passwordPrompt() {
  if (process.env.ACCOUNT_PASSWORD) return process.env.ACCOUNT_PASSWORD;
  if (!process.stdin.isTTY)
    throw new Error(
      'Use an interactive terminal or set ACCOUNT_PASSWORD for automated account creation.'
    );
  process.stdout.write('Password (12–128 characters, hidden): ');
  return new Promise((resolve, reject) => {
    let value = '';
    process.stdin.setRawMode(true);
    process.stdin.resume();
    process.stdin.setEncoding('utf8');
    const finish = () => {
      process.stdin.setRawMode(false);
      process.stdin.pause();
      process.stdin.off('data', receive);
      process.stdout.write('\n');
    };
    const receive = (chunk) => {
      for (const char of chunk) {
        if (char === '\u0003') {
          finish();
          reject(new Error('Cancelled.'));
          return;
        }
        if (char === '\r' || char === '\n') {
          finish();
          resolve(value);
          return;
        }
        if (char === '\u007f' || char === '\b') value = value.slice(0, -1);
        else if (char >= ' ' && value.length < 129) value += char;
      }
    };
    process.stdin.on('data', receive);
  });
}
try {
  const [operation, login, name] = process.argv.slice(2);
  if (
    !['teacher', 'reset'].includes(operation) ||
    !login ||
    (operation === 'teacher' && !name)
  )
    throw new Error(
      'Usage: npm run account -- teacher EMAIL "Full name" OR npm run account -- reset EMAIL_OR_ROLL'
    );
  const password = await passwordPrompt();
  if (operation === 'teacher')
    createUser({ role: 'teacher', login, name, password });
  else {
    if (password.length < 12 || password.length > 128)
      throw new Error('Passwords must contain 12–128 characters.');
    const user = db()
      .prepare('SELECT id FROM users WHERE login=?')
      .get(login.trim().toLowerCase());
    if (!user) throw new Error('Account not found.');
    db()
      .prepare('UPDATE users SET password_hash=? WHERE id=?')
      .run(passwordHash(password), user.id);
    db().prepare('DELETE FROM logins WHERE user_id=?').run(user.id);
  }
  console.log('Account saved.');
} catch (error) {
  console.error(error.message);
  process.exitCode = 1;
}
