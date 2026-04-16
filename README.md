# Web File Manager

A PHP web-based file manager designed for service providers to manage isolated file spaces for multiple clients. Built on top of [Tiny File Manager](https://github.com/prasathmani/tinyfilemanager), extended with a full multi-user system, role-based access control, and a MySQL backend.

---

## Features

### File Operations
- **Browse** — Directory listing with name, size, permissions, modified date, and owner
- **Upload** — Drag-and-drop chunked uploads via Dropzone.js (supports files up to ~5 GB; ~2 MB chunks)
- **Upload from URL** — Fetch and save a remote file directly to the server
- **Download** — Streaming file download
- **Create** — Create new files or folders with extension allowlist enforcement
- **Delete** — Delete single items or mass-delete via checkbox selection
- **Rename** — Rename files and folders with character and extension validation
- **Copy / Move** — Copy or move single items or multiple selected items; duplicates get a timestamp suffix
- **Edit** — Full-featured Ace.js code editor with syntax highlighting, fullscreen mode, word wrap, and theme/font-size controls; falls back to a plain textarea
- **View** — Read-only file view with highlight.js syntax highlighting
- **Archive** — Create and extract `.zip` archives (via PHP's `ZipArchive`) and `.tar` archives (via `PharData`)
- **Backup** — One-click backup copies a file to `filename-ddMonyy-HHmmss.bak`
- **chmod** — Recursive file permission change
- **Online Preview** — Open documents in Google Docs or Microsoft Office Online viewer (configurable)
- **Search** — Quick inline search; modal Advanced Search scans folders and subfolders recursively

All write operations are disabled when readonly mode is active.

---

### Authentication & Security
- **Session-based login** with a named session (`FM_SESSION_ID`)
- **Bcrypt password hashing** — `password_hash(PASSWORD_DEFAULT)` / `password_verify()`
- **CSRF protection** — 32-byte random token per session, validated with `hash_equals()` on every state-changing request and AJAX call
- **Brute-force mitigation** — 1-second delay on every login attempt
- **IP access control** — Configurable `OFF` / `AND` / `OR` ruleset with whitelist and blacklist arrays
- **Path traversal prevention** — `realpath()` checked against the user's allowed root on every file operation
- **XSS prevention** — All user-supplied output goes through `htmlspecialchars()`
- **Parameterized queries** — PDO with `EMULATE_PREPARES=false` throughout

---

### Password Reset
1. User submits their email on the "Forgot Password?" form
2. A 64-character hex token is generated, stored in the database with a 24-hour expiry, and emailed to the user
3. The reset link validates the token and expiry, then accepts a new password (minimum 6 characters, confirmed twice)
4. On success, the password is updated and the token is deleted

---

### User Roles & Permissions

| Role | Access |
|------|--------|
| **Admin** | Full read/write access to the entire `clients_files/` base directory; access to the Admin Panel |
| **Client** | Restricted to one or more explicitly assigned directories; cannot access the Admin Panel |

- Client users assigned multiple directories see a **directory switcher** dropdown in the navbar
- A **global readonly flag** or per-user readonly mode can be configured to block all write operations
- Admins cannot delete their own account

---

### Admin Panel
- **User list** — View all users with their assigned directories, role, and email
- **Create user** — Set username, password, email, role, and one or more directory paths; directories are created on the filesystem automatically; credentials are emailed to the new user
- **Edit user** — Update any field; optionally change the password; replace directory assignments atomically
- **Delete user** — Remove a user and all their directory assignments (cascaded in the database)
- **Send password reset link** — Admin can trigger a reset email for any user

---

## Database

MySQL database: `web_file_manager` (utf8mb4)

### `users`
| Column | Type | Notes |
|--------|------|-------|
| `id` | INT UNSIGNED | Primary key, auto-increment |
| `username` | VARCHAR(100) | Unique |
| `password` | VARCHAR(255) | Bcrypt hash |
| `email` | VARCHAR(255) | |
| `role` | ENUM(`admin`, `client`) | Default: `client` |
| `created_at` / `updated_at` | DATETIME | Auto-managed timestamps |

### `user_directories`
| Column | Type | Notes |
|--------|------|-------|
| `id` | INT UNSIGNED | Primary key |
| `user_id` | INT UNSIGNED | Foreign key → `users.id` (cascade delete) |
| `directory_path` | VARCHAR(500) | Absolute path on the server |
| `created_at` | DATETIME | |

### `password_resets`
| Column | Type | Notes |
|--------|------|-------|
| `id` | INT UNSIGNED | Primary key |
| `user_id` | INT UNSIGNED | Foreign key → `users.id` (cascade delete) |
| `token` | VARCHAR(64) | Unique, indexed |
| `expires_at` | DATETIME | Indexed; 24-hour window |
| `created_at` | DATETIME | |

**Default admin credentials (seed):** `admin` / `admin@123` — **change immediately after first login.**

---

## Installation

### Requirements
- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.3+
- Extensions: `pdo_mysql`, `zip`, `phar`, `mbstring`, `fileinfo`

### Steps

1. **Clone or copy** the project to your web server's document root (e.g., `htdocs/web-file-manager`).

2. **Create the database** and import the schema:
   ```sql
   CREATE DATABASE web_file_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   USE web_file_manager;
   SOURCE database/schema.sql;
   ```

3. **Edit `config.php`** with your settings:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'web_file_manager');
   define('DB_USER', 'your_db_user');
   define('DB_PASS', 'your_db_password');
   define('APP_URL', 'http://your-domain.com/web-file-manager');
   define('FM_CLIENTS_DIR', './clients_files');
   ```

4. **Create the clients directory** and ensure it is writable:
   ```bash
   mkdir clients_files
   chmod 755 clients_files
   ```

5. **Log in** at `http://your-domain.com/web-file-manager` with the default admin credentials and change the password.

---

## Configuration Reference

| Setting | Default | Description |
|---------|---------|-------------|
| `FM_CLIENTS_DIR` | `./clients_files` | Base directory for all client file spaces |
| `DB_HOST/NAME/USER/PASS` | `localhost` / `file_manager` / `root` | MySQL connection |
| `APP_TITLE` | `File Manager` | Page title shown in the browser and UI |
| `APP_URL` | `http://localhost/web-file-manager` | Base URL used in emails |
| `MAIL_FROM` / `MAIL_FROM_NAME` | `noreply@example.com` | Email sender identity |
| `RESET_TOKEN_EXPIRY_HOURS` | `24` | Password reset link lifetime (hours) |
| `$edit_files` | `true` | Enable the Ace.js code editor |
| `$use_highlightjs` | `true` | Enable syntax highlighting in file view |
| `$highlightjs_style` | `vs` | highlight.js CSS theme name |
| `$default_timezone` | `Etc/UTC` | PHP timezone |
| `$datetime_format` | `m/d/Y g:i A` | Date/time display format |
| `$path_display_mode` | `full` | `full`, `relative`, or `host` |
| `$allowed_file_extensions` | `` (all) | Comma-separated allowlist for create/rename |
| `$allowed_upload_extensions` | `` (all) | Comma-separated allowlist for uploads |
| `$online_viewer` | `google` | `google`, `microsoft`, or `false` |
| `$sticky_navbar` | `true` | Fix navbar to the top of the page |
| `$max_upload_size_bytes` | `5000000000` | Max upload size (~5 GB) |
| `$upload_chunk_size_bytes` | `2000000` | Chunk size per upload request (~2 MB) |
| `$ip_ruleset` | `OFF` | IP access control: `OFF`, `AND`, or `OR` |
| `$ip_whitelist` / `$ip_blacklist` | `127.0.0.1` | IP address arrays for access control |
| `$global_readonly` | `false` | Disable all write operations globally |

Runtime UI preferences (theme, language, show hidden files, column visibility, error reporting) are persisted per-server in `settings.json` via an AJAX settings endpoint.

---

## UI & Frontend

| Library | Version | Purpose |
|---------|---------|---------|
| Bootstrap | 5.3 | Layout and UI components |
| Font Awesome | 4.7 | Icons |
| Dropzone.js | 5.9 | Drag-and-drop file uploads |
| Ace.js | latest (CDN) | Advanced code editor |
| highlight.js | 11.9 | Read-only syntax highlighting |

- **Dark / Light theme** toggle, persisted to `settings.json`
- Responsive navbar with **breadcrumb path navigation**
- Modals for: create item, copy/move destination, chmod, advanced search, settings, remote URL upload, and archive management

---

## Project Structure

```
├── config.php               # Application configuration
├── index.php                # Main entry point and file browser
├── reset_password.php       # Password reset flow
├── settings.json            # Runtime UI settings (auto-generated)
├── actions/
│   ├── ajax.php             # AJAX endpoint handlers
│   └── file_actions.php     # File operation handlers
├── database/
│   └── schema.sql           # Database schema and seed data
├── inc/
│   ├── auth.php             # Authentication, CSRF, IP control
│   ├── classes.php          # FM_Config, FM_Zipper, FM_Zipper_Tar
│   ├── db.php               # PDO database connection
│   ├── helpers.php          # Utility functions (path, size, MIME, etc.)
│   ├── i18n.php             # Internationalization string lookup
│   └── users.php            # User CRUD and directory management
└── templates/
    ├── footer.php
    ├── header.php
    ├── login.php
    ├── nav.php
    └── admin/
        ├── user_form.php    # Create/edit user form
        └── users.php        # Admin user list
```

---

## License

See [LICENSE](LICENSE).
