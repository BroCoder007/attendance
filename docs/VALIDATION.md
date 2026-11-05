# Validation — 5 November 2026

Tested locally on Windows with Node 24.18.0, SvelteKit 3.1.1, Svelte 5.57.3, Vite 8.3.6 and adapter-node 6.0.1.

- `npm test`: 11 domain/security tests passed, using an isolated in-memory database.
- `npm run build`: production server/client build passed.
- `npm run test:http`: full HTTP regression scenario passed against a temporary SQLite database. Covers login, roles, teacher isolation, current code check-in, duplicate retries, signed QR check-in, CSV, logout, closed windows, CSRF rejection and the browser’s six-digit input pattern.
- Browser checks with synthetic accounts: staff sign-in, class creation, code rotation, student sign-in/check-in and the resulting staff count. Checked desktop and 390 × 844 layouts for staff/student pages; no horizontal document overflow was observed. The six-digit form was tested through the browser, not only by calling the server.
- `npm audit --omit=dev`: no known production dependency advisories. Full audit: four low-severity entries from one transitive build-tool `deepmerge` advisory; see SECURITY.md.
- `git diff --check`: passed.

`preview.png` shows synthetic records, not real students. Native camera hardware, real classroom networking, reverse-proxy address handling, backup restoration and historical MySQL imports were not exercised in this local test. No live school database was changed and no production deployment was made.
