# Repository Structure

This document describes the organization of the RICEVUTE repository after the reorganization.

## Directory Structure

```
RICEVUTE/
├── api/                      # API endpoints (REST JSON)
│   ├── cerca_prodotto.php
│   ├── error_handler.php
│   ├── genera_report.php
│   ├── gestisci_dati.php
│   ├── gestisci_impostazioni.php
│   ├── require_admin.php
│   ├── response.php
│   ├── ripeti_comanda.php
│   └── salva_ordine.php
│
├── config/                   # Configuration files
│   ├── check_login.php       # Authentication logic
│   └── db_connection.php     # Database connection
│
├── docs/                     # Documentation
│   ├── screenshots/          # Application screenshots
│   ├── summaries/            # Implementation summaries
│   ├── API.md
│   ├── INSTALL.md
│   ├── README.md
│   ├── worker_comande.service
│   └── ...
│
├── logs/                     # Application logs (gitignored except .gitkeep)
│
├── public/                   # Web-accessible files
│   ├── assets/
│   │   ├── css/              # Stylesheets
│   │   │   ├── style.css
│   │   │   └── style_home.css
│   │   └── js/               # JavaScript files
│   │       ├── admin.js
│   │       ├── cassa.js
│   │       └── report.js
│   ├── admin.php             # Admin panel
│   ├── cassa.php             # Cash register / Order entry
│   ├── login.php             # Login page
│   ├── logout.php            # Logout handler
│   └── report.php            # Reports page
│
├── index.html                # Homepage (GitHub Pages entry point)
│
├── scripts/                  # Utility scripts
│   ├── test_discount_validation.sh
│   ├── test_validation.sh
│   └── worker_process_comande.php  # CLI worker for printing
│
├── sql/                      # Database schemas and migrations
│   ├── legacy/               # Archived/old SQL files
│   ├── migrations/           # Database migrations
│   └── schema.sql            # Current database schema
│
├── src/                      # PHP source classes
│   └── Utils/
│       └── InputValidator.php
│
├── tools/                    # Development and testing tools
│   └── tests/                # Test files
│
├── CNAME                     # GitHub Pages CNAME
└── LICENSE                   # Project license
```

## Key Directories Explained

### `/api`
Contains all REST API endpoints. All files return JSON responses with a uniform structure:
```json
{
  "success": true/false,
  "data": {...},
  "error": "..."
}
```

### `/config`
Configuration and authentication files:
- **db_connection.php**: Database connection setup (PDO)
- **check_login.php**: Handles login authentication and session management

### `/docs`
Complete project documentation:
- **summaries/**: Implementation notes and feature summaries
- **screenshots/**: UI screenshots
- Various markdown files documenting features, API, installation, etc.

### `/public`
Web-accessible files organized by type:
- **assets/css/**: All stylesheets
- **assets/js/**: All JavaScript files
- **.php**: Main application pages (admin, cassa, login, report)

**Note**: The homepage `index.html` is located in the root directory for GitHub Pages compatibility.

### `/scripts`
Command-line scripts and workers:
- **worker_process_comande.php**: Processes pending print jobs from the COMANDE table

### `/sql`
Database-related files:
- **schema.sql**: Current database schema
- **migrations/**: SQL migration files
- **legacy/**: Archived old schema files

### `/src`
PHP classes and utilities (PSR-4 autoloadable if needed)

### `/tools/tests`
Testing utilities and test files

## Path References

After reorganization, files reference each other as follows:

### From `/public/*.php` files:
```php
require_once '../config/db_connection.php';  // Database connection
```

### From `/api/*.php` files:
```php
require_once '../config/db_connection.php';  // Database connection
```

### From `/scripts/worker_process_comande.php`:
```php
require_once __DIR__ . '/../config/db_connection.php';
```

### HTML asset references in `/public/*.php`:
```html
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/admin.js"></script>
```

### HTML asset references in `/index.html` (root):
```html
<link rel="stylesheet" href="public/assets/css/style_home.css">
<a href="public/cassa.php">Link to Cassa</a>
```

## Deployment Notes

1. **GitHub Pages**: The repository is configured for GitHub Pages deployment with the homepage (`index.html`) in the root directory. This allows GitHub Pages to serve the site correctly.

2. **Web Server Document Root**: Point to `/public` directory to keep configuration files outside the web root for better security.

3. **Alternative (Current Setup)**: If document root is the repository root, files are accessed as:
   - Homepage: `/index.html` (GitHub Pages entry point)
   - Login: `/public/login.php`
   - Admin: `/public/admin.php`
   - API: `/api/cerca_prodotto.php`

4. **Security**: The `/config` directory should be protected from direct web access via `.htaccess` or web server configuration.

## Migration from Old Structure

Files were moved as follows:

| Old Location | New Location |
|--------------|--------------|
| `/*.css` | `public/assets/css/*.css` |
| `/*.js` (page scripts) | `public/assets/js/*.js` |
| `/*.php` (pages) | `public/*.php` |
| `db_connection.php` | `config/db_connection.php` |
| `check_login.php` | `config/check_login.php` |
| `summary/*` | `docs/summaries/*` |
| `utilities/creazione_tabelle.sql` | `sql/legacy/creazione_tabelle.sql` |

All file references in the codebase have been updated accordingly.
