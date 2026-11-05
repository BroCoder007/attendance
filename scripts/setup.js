import { randomBytes } from 'node:crypto';
import { writeFileSync } from 'node:fs';
try {
  writeFileSync(
    '.env',
    `AUTH_SECRET=${randomBytes(32).toString('hex')}\nDB_PATH=data/rollcall.sqlite\nORIGIN=http://127.0.0.1:5173\nPUBLIC_TIME_ZONE=Asia/Kolkata\n`,
    { flag: 'wx', mode: 0o600 }
  );
  console.log(
    'Created .env with a private signing key. Next: npm run account -- teacher your@email.example "Your name"'
  );
} catch (error) {
  if (error.code === 'EEXIST')
    console.log('.env already exists; it has been left unchanged.');
  else throw error;
}
