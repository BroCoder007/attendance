# Security model

- Scrypt password hashes with random salts; no default production credentials. Staff accounts are created from the server terminal.
- Random 256-bit login tokens stored only as SHA-256 hashes in SQLite; seven-day expiry. HttpOnly, SameSite cookies; Secure on HTTPS. Password resets and pauses revoke existing sessions.
- SvelteKit’s same-origin form protection is retained. Writes use POST. Parameterized queries and owner checks protect class pages, reports and staff writes. Students check in only as themselves against their teacher’s class.
- Two-minute random class codes, two-minute HMAC-signed QR passes, rate limits and optional IPv4/IPv6 network restrictions. A QR can be re-presented during its lifetime, but a unique attendance key prevents duplicate records. QR possession alone does not establish physical presence.
- Database transactions and retry-safe inserts. Expired/closed windows reject student and staff check-in.
- Private responses use `no-store`; CSV output neutralizes formulas. No attendance information, QR or password is sent to an AI service, analytics vendor or third-party QR generator. Fonts are self-hosted.

This is a single-instance classroom application, not an identity-verification system. It cannot stop credential/code sharing. Rate limits live in the local database. Staff may override code/network checks when confirming presence in person and can access their own roster’s personal information.

Use HTTPS, protect the database/backups/signing key and audit proxy settings. Follow your institution’s retention policy. This release has not had an independent security assessment.

Report vulnerabilities privately to the repository owner through an available private channel. Never post student records, live tokens, passwords or signing keys in public issues.

Dependency check (5 November 2026): the production dependency audit reports no known advisories. The full dependency audit reports four low-severity entries caused by the build toolchain’s transitive `deepmerge` advisory ([GHSA-gcqx-w48m-w2h4](https://github.com/advisories/GHSA-gcqx-w48m-w2h4)). No patched compatible `deepmerge` release was available at this check. Do not process untrusted build configuration; recheck dependencies before deploying.
