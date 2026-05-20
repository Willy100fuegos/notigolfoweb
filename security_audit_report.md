# Security Audit Report – NotiGolfo CMS

**Date:** 2026-05-08

---

## 1. Overview
The project is a flat‑file CMS with a small admin panel, Redis‑based counters, a JSON index, and automatic SEO generation.  Most security mechanisms (CSRF tokens, password hashing, prepared statements) have already been added, but there are several **high‑impact** areas that need hardening before the site is exposed to production traffic.

---

## 2. Findings
| Area | Severity | Description | Evidence |
|------|----------|-------------|----------|
| **Plain‑text FTP credentials** | Critical | `/.vscode/sftp.json` contains the FTP username & password in clear text. If the repository is ever exposed (e.g., via a public Git remote) the credentials are compromised. | `sftp.json` content shows password `hU=95DW-6#1JoVlr`.
| **Potential exposure of configuration files** | High | The `includes/` directory and `.vscode/` folder are reachable through the web root unless explicitly blocked with an `.htaccess` or server config. | No `.htaccess` files shown in the listing.
| **Session fixation** | Medium | After a successful login the session ID is not regenerated, allowing an attacker to force a known session ID before authentication. | `admin/login.php` does not call `session_regenerate_id(true)`.
| **Missing `HttpOnly`/`Secure` flags on cookies** | Medium | Session cookies are created by default PHP settings; without `session.cookie_httponly` and `session.cookie_secure` they can be accessed via JavaScript or sent over HTTP. | No explicit settings in the codebase.
| **Redis unauthenticated** | Medium | Redis is instantiated with default host/port and no password. If the server is reachable from the internet, an attacker could flush counters or enumerate keys. | `article.php` connects with `new Redis(); $redis->connect('127.0.0.1', 6379);`.
| **HTTPS not enforced** | Medium | No redirect to HTTPS or `Strict-Transport‑Security` header is set. All traffic can be intercepted. | No header handling in `includes/header.php`.
| **Insufficient input validation on uploads** | Medium | `admin/upload_inline.php` accepts file uploads but the source code was not inspected for MIME/type checks. Unrestricted uploads can lead to remote code execution. |
| **Error display in production** | Low | No configuration to silence `display_errors`. Stack traces could be leaked on failures. |
| **Rate‑limiting / brute‑force protection** | Low | Login form does not implement a delay or lockout after repeated failures. |
| **Content‑Security‑Policy (CSP) missing** | Low | No CSP header, allowing injection of malicious scripts via markdown or user‑generated content. |

---

## 3. Recommendations
### 3.1 Credential Management
- **Remove `sftp.json` from version control** and keep it outside the web root.  Store FTP credentials in environment variables (`FTP_USER`, `FTP_PASS`) and reference them from VS Code’s `sftp.json` using `${env:FTP_USER}` syntax.
- Add a **Git‑ignore rule**: `/.vscode/sftp.json`.
- Consider switching to **SFTP over FTPS** and use key‑based authentication.

### 3.2 Server‑Side Access Control
- Place an **`.htaccess`** (or equivalent Nginx rule) in the `includes/` and `.vscode/` directories:
  ```apacheconf
  Order deny,allow
  Deny from all
  ```
- Ensure the `public_html` directory is the only exposed folder.

### 3.3 Session Hardening
- After successful login, call `session_regenerate_id(true);`.
- In a central init file (e.g., `includes/config_manager.php`) set:
  ```php
  ini_set('session.cookie_httponly', 1);
  ini_set('session.cookie_secure', 1); // only if HTTPS is used
  ini_set('session.use_strict_mode', 1);
  ```

### 3.4 Enforce HTTPS & HSTS
- In `includes/header.php` add:
  ```php
  if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
      header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
      exit();
  }
  header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
  ```
- Ensure the server has a valid TLS certificate.

### 3.5 Redis Security
- Configure Redis with a strong password (`requirepass`) and connect using `$redis->auth('YOUR_PASSWORD');`.
- Bind Redis to `127.0.0.1` only (already done) and restrict access via firewall.

### 3.6 File Upload Validation
- In `admin/upload_inline.php` validate MIME type, file extension, and size.
- Store uploads **outside** the web root and serve them via a script that checks permissions.
- Rename files with a random hash to avoid path traversal.

### 3.7 Error Handling
- In production (`includes/config_manager.php` or `.htaccess`), set:
  ```php
  ini_set('display_errors', 0);
  ini_set('log_errors', 1);
  ini_set('error_log', __DIR__.'/../logs/php_errors.log');
  ```

### 3.8 Brute‑Force & Rate Limiting
- Track failed login attempts per IP in Redis and block after, e.g., **5** attempts within **15 min**.
- Add a small `usleep(500000);` delay on each login attempt to slow automated attacks.

### 3.9 Content‑Security‑Policy
- Add a CSP header in `header.php`:
  ```php
  header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; img-src 'self' data:; object-src 'none';");
  ```

### 3.10 Review of Database Queries
- Verify **all** queries use prepared statements (`$pdo->prepare`) with bound parameters.  A quick `grep` for `query(` in `admin/` shows only a few read‑only queries, but double‑check any dynamic `WHERE` clauses.

### 3.11 Header & Meta Hardening
- Ensure `includes/header.php` includes the following security headers:
  ```php
  header('X-Frame-Options: SAMEORIGIN');
  header('X-Content-Type-Options: nosniff');
  header('Referrer-Policy: strict-origin-when-cross-origin');
  ```

---

## 4. Quick Wins (5‑minute actions)
1. **Add `session_regenerate_id(true);`** at the end of successful login (`admin/login.php`).
2. **Create `.htaccess`** in `includes/` and `.vscode/` with `Deny from all`.
3. **Set PHP error display off** in `includes/config_manager.php`.
4. **Add CSP**, `X‑Frame‑Options`, and `X‑Content‑Type‑Options` headers in `includes/header.php`.
5. **Remove `sftp.json` from the repo** and add it to `.gitignore`.

---

## 5. Long‑Term Roadmap
| Milestone | Tasks |
|-----------|-------|
| **Week 1** | Implement session hardening, HTTPS redirect, security headers, and `.htaccess` files.
| **Week 2** | Migrate FTP credentials to environment variables, lock down Redis, add upload validation.
| **Week 3** | Introduce login rate‑limiting, configure error logging, and perform a penetration‑test scan (e.g., OWASP ZAP).
| **Month 1** | Deploy a CI/CD pipeline that lints for security (e.g., `phpcs-security`), runs static analysis, and fails on secrets in the repo.

---

## 6. Conclusion
The core architecture is sound, but production‑grade security requires the mitigations listed above.  Address the **critical** credential exposure first, then apply the medium‑level hardening steps.  After those changes, the site will be considerably more resistant to common web‑application attacks.

---

*Prepared by Antigravity – AI coding assistant*
