# From WAMP to SvelteKit

| Previous application | SvelteKit application |
| --- | --- |
| Apache/PHP routes | SvelteKit loads, form actions and server endpoints |
| MySQL via PDO | Node SQLite on a persistent local disk |
| Teacher bcrypt login / student roll-only login | Scrypt password authentication for both roles |
| Permanent browser device UUID | Account authentication, expiring codes, optional network restriction and staff-confirmed QR |
| Global student directory | One teacher-owned roster per student account |
| PHP QR endpoint | Owner-authorized action with signed, expiring student QR |

The old source is in `legacy/wamp/`, outside the new server’s served files. The previous database is not changed.

## Cutover

1. Back up the MySQL database, PHP source and deployment settings. Export historical reports.
2. Install separately on Node 24, run `npm ci` and `npm run setup`, then create staff accounts with `npm run account`.
3. Enrol students in the correct roster and issue individual passwords privately. Students previously had no password; PHP bcrypt staff hashes are not interchangeable with scrypt hashes, so staff need new passwords too.
4. Test opening a class, check-in, closure and export with test accounts. Confirm timezone, phone access and proxy address handling.
5. Cut over outside attendance collection. Configure the production HTTPS `ORIGIN`, persistent database and reverse proxy, then build/start the Node service.
6. Retain old records as a restricted read-only archive under your retention policy. Remove public access to the PHP app. Keep a rollback route until the new deployment is verified.

## Historical records

There is **no automatic historical importer in this release**. Keep existing MySQL records and exports available to authorized administrators. Do not copy a MySQL dump into SQLite or run the legacy `schema.sql` against the new database.

A custom import needs explicit teacher/student/session mappings. The old global student table may include students attending multiple teachers; this version supports one owner per student account. Resolve that relationship first, preserve timestamps/methods and reconcile totals against the original export. Current CSV reports use today’s roster, not historical membership; keep original reports for historical reporting.

The permanent browser-device lock was removed because a cookie cannot reliably prove identity or location. Duplicate prevention remains a database invariant per class/student. Staff verification is required where attendance assurance matters.
