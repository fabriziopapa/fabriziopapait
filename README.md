# fabriziopapait

Source repository for **fabriziopapa.it**, the personal website of Fabrizio Papa.

The project is intentionally lightweight: HTML, CSS and JavaScript on the frontend, with a small PHP backend for the contact service and supporting public security resources under `.well-known`.

> **Security-first repository:** credentials, API keys, tokens, private keys, logs and local configuration files must never be committed.

---

## Overview

This repository contains the public source code and assets used by **fabriziopapa.it**.

Main components:

- Static HTML pages
- Custom CSS and JavaScript
- PHP contact endpoint
- PHPMailer integration
- Cloudflare Turnstile verification
- Friendly Captcha verification
- OpenPGP public key
- Web Key Directory — WKD
- `security.txt`
- PKI validation resources
- Audio and multimedia assets
- Custom HTTP error pages

The repository contains **public application code only**.

Runtime secrets are maintained outside version control.

---

## Technology stack

| Layer | Technology |
|---|---|
| Frontend | HTML5, CSS, JavaScript |
| Backend | PHP |
| Mail | PHPMailer |
| Anti-abuse | Cloudflare Turnstile |
| Anti-abuse | Friendly Captcha |
| Security metadata | `security.txt` |
| Cryptography | OpenPGP / WKD |
| TLS validation | `.well-known/pki-validation` |
| Version control | Git / GitHub |

---

## Repository structure

```text
fabriziopapait/
│
├── .well-known/
│   ├── openpgpkey/
│   │   ├── hu/
│   │   └── policy
│   ├── pki-validation/
│   └── security.txt
│
├── assets/
│   ├── audio/
│   ├── css/
│   └── js/
│
├── phpmailer/
│   ├── language/
│   ├── Exception.php
│   ├── PHPMailer.php
│   └── SMTP.php
│
├── .gitignore
├── .htaccess
├── 404.html
├── caseback.html
├── complicazioni.html
├── contact.php
├── estratto-accrual.html
├── estratto.txt
├── index.html
└── pgp.asc
```

---

## Local configuration

Sensitive runtime configuration is **not stored in this repository**.

`contact.php` loads a local configuration file:

```php
require __DIR__ . '/config.local.php';
```

The local file contains the runtime credentials required by the contact service, for example:

```php
<?php

$SMTP_PASS = '...';
$TURNSTILE_SECRET = '...';
$FRIENDLY_CAPTCHA_API_KEY = '...';
```

`config.local.php` is intentionally excluded through `.gitignore`.

### Never commit `config.local.php`

The following types of information must remain outside Git:

```text
Passwords
API keys
Authentication tokens
Private cryptographic keys
SMTP credentials
Captcha secrets
Environment files
Server logs
Database credentials
Backups containing secrets
```

For hardened production environments, environment variables or a dedicated secrets manager should be preferred over credentials stored directly in application source files.

---

## Ignored sensitive files

The repository uses defensive `.gitignore` rules including:

```gitignore
# Secrets
.env
.env.*
*.pem
*.key
*.p12
*.pfx

# Logs
*.log
error_log

# Archives / backups
*.tar
*.tar.gz
*.tgz
*.zip
*.bak
*.backup

# Local configuration
config.local.php
secrets.php
credentials.php

# Local backups
contact.php.backup-*

# Operating system
.DS_Store
Thumbs.db
```

`.gitignore` is only one layer of protection. Secrets that have already been committed must be considered exposed and rotated even after removal from the repository.

---

## Security model

Security is treated as part of the project architecture rather than as a post-deployment addition.

### Secret isolation

Application secrets are separated from source code and excluded from Git.

The public repository must never contain:

```text
SMTP passwords
Turnstile secrets
Friendly Captcha API keys
Private OpenPGP keys
SSH private keys
TLS private keys
Access tokens
Session secrets
Production logs
Database dumps
```

### GitHub repository protection

For the public GitHub repository, the recommended configuration is:

- GitHub Secret Scanning enabled
- Push Protection enabled
- Dependency security alerts enabled
- Code scanning enabled where applicable
- Branch protection or repository rulesets for important branches
- Security advisories enabled
- Periodic review of the repository's Security section

Push Protection is especially important because preventing a credential from entering Git history is preferable to removing it afterward.

---

## Responsible vulnerability disclosure

Security-related information is published through:

```text
/.well-known/security.txt
```

Security researchers should use the contact information provided there for responsible disclosure.

A dedicated `SECURITY.md` may also be maintained at repository level to describe supported versions, reporting procedures and disclosure expectations.

Please do **not** disclose vulnerabilities through public GitHub issues when the report contains exploitable technical details.

---

## OpenPGP

The repository contains:

```text
pgp.asc
```

This file contains the **public OpenPGP key only**.

The private key must never be stored in this repository.

The public key may be used for identity verification, encrypted communication and verification of signed material where applicable.

---

## Web Key Directory

Public OpenPGP discovery resources are available under:

```text
.well-known/openpgpkey/
```

These files are intentionally public and form part of the OpenPGP Web Key Directory infrastructure.

They must not contain private key material.

---

## PKI validation

Certificate validation resources are stored under:

```text
.well-known/pki-validation/
```

These files are intentionally publicly accessible when required for domain or certificate validation.

---

## Contact service

The contact endpoint is implemented in:

```text
contact.php
```

The service integrates:

```text
PHPMailer
Cloudflare Turnstile
Friendly Captcha
```

Secrets required by these services are loaded from the local non-versioned configuration.

No production password or API secret should appear directly inside `contact.php`.

---

## Local security checks before a push

Before publishing changes, a basic secret check can be performed with Git:

```powershell
git grep -n -i -E "password|passwd|secret|api[_-]?key|authorization|bearer"
```

Results must be manually reviewed because legitimate library code may contain terms such as `Password`, `Token` or `Secret`.

To search for accidentally committed private keys:

```powershell
git grep -n -E "BEGIN (RSA |EC |OPENSSH |DSA )?PRIVATE KEY|BEGIN PGP PRIVATE KEY BLOCK|BEGIN PGP SECRET KEY BLOCK"
```

A clean repository should return no private-key material.

Verify ignored local configuration with:

```powershell
git check-ignore -v config.local.php
```

And check that sensitive files are not tracked:

```powershell
git ls-files | Select-String "config.local.php|contact.php.backup|error_log|\.tar$|\.log$"
```

---

## Development workflow

A minimal workflow for changes is:

```bash
git status
git diff
git add -A
git diff --cached
git commit -m "Describe the change"
git push
```

Always inspect the staged diff before committing:

```bash
git diff --cached
```

This is particularly important when modifications involve configuration, authentication, server files or security-related code.

---

## Deployment

The public repository does **not** contain all information required to reproduce the production environment automatically.

A production deployment additionally requires:

```text
Local secret configuration
PHP runtime
Web server configuration
Mail infrastructure
TLS configuration
Captcha service configuration
Appropriate filesystem permissions
```

`config.local.php` must be provisioned independently on the destination server.

It must never be copied into the Git repository.

---

## Dependency management

PHPMailer is currently present inside:

```text
phpmailer/
```

Dependencies should be kept updated and security advisories monitored.

For future evolution of the project, package-managed dependencies with reproducible version locking and automated vulnerability monitoring are preferable to unmanaged dependency copies.

---

## Security maintenance

When a credential is accidentally exposed:

1. Revoke or rotate it immediately.
2. Replace it in the production environment.
3. Remove it from the current source tree.
4. Remove it from Git history when necessary.
5. Review logs for possible unauthorized use.
6. Run another repository-wide secret scan.
7. Verify GitHub security alerts.
8. Enable or review Push Protection.

Deleting a secret from the latest commit alone does not invalidate copies that may already exist in Git history, clones, caches or external indexes.

---

## Public versus private material

### Appropriate for this repository

```text
HTML
CSS
JavaScript
PHP application logic
Public OpenPGP keys
security.txt
WKD public material
PKI validation files
Public multimedia assets
Documentation
```

### Must remain private

```text
Passwords
API secrets
Private keys
Production configuration
Access tokens
Personal authentication material
Internal logs
Server backups
Database exports
Unredacted diagnostic archives
```

---

## Repository integrity

The `main` branch represents the public source tree.

Changes affecting authentication, cryptography, form processing, mail delivery or `.well-known` resources should receive additional review before deployment.

Where possible, security-sensitive modifications should remain small, auditable and reversible.

---

## Privacy

Production logs and diagnostic data are intentionally excluded from the repository.

Logs can contain information such as:

```text
IP addresses
Request metadata
Server filesystem paths
Error details
User-agent strings
Email addresses
Application diagnostics
```

Such information should be retained only where operationally necessary and according to the applicable privacy and retention requirements.

---

## License

No software license should be assumed unless a dedicated `LICENSE` file is present in this repository.

A license should be added explicitly if reuse, redistribution or contribution rights are intended to be granted.

---

## Maintainer

**Fabrizio Papa**

Repository:

`fabriziopapa/fabriziopapait`

Website:

`https://fabriziopapa.it`

---

## Security status

**Security baseline reviewed: August 2026**

The repository architecture is designed so that public source code and private runtime credentials remain strictly separated.

Security is an ongoing process: dependencies, secrets, repository settings and production configuration should be periodically reviewed as the project evolves.