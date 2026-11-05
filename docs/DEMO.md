# Local preview with synthetic data

The demo uses a separate database and intentionally published demo credentials. Do not expose it to the internet or use its passwords for real people.

```sh
npm ci
npm run setup
npm run demo
```

In the ignored `.env`, change `DB_PATH` to `data/demo.sqlite`, then run `npm run dev`. Demo setup refuses to overwrite an existing demo database and does not change `data/rollcall.sqlite`.

| Role | Login | Password |
| --- | --- | --- |
| Staff | `staff@example.test` | `Classroom-demo-2026` |
| Student | `cs001` through `cs008` | `Student-demo-2026` |

All names are synthetic. The first five students have a data-structures attendance record; others can try code check-in. Staff can refresh expired codes. Sample windows end 30/60 minutes after setup; create another class if they have ended.

When finished, stop the server and restore `DB_PATH=data/rollcall.sqlite`. Demo accounts are never automatically created by the application. Demo setup refuses to run with `NODE_ENV=production`.
