# Installation Scripts Security Notice

## IMPORTANT: Dangerous Scripts Disabled

The following scripts have been **DISABLED** for security reasons:

- `install_php.php.disabled`
- `install-composer.php.disabled`

### Why These Scripts Are Dangerous

These scripts execute system commands (`shell_exec()`, `exec()`) and should **NEVER** be accessible on a production server. They pose severe security risks:

1. **Remote Code Execution (RCE)** - Attackers could exploit these to run arbitrary commands
2. **Server Compromise** - Full system access possible if exploited
3. **Data Breach** - Could lead to database and file system access

### If You Need to Run Installation

1. **Only run locally** or on a secure development server
2. **Never expose** these scripts on a public-facing server
3. **Delete immediately** after installation is complete

### To Re-enable (Development Only):

```bash
# Only do this on a secure development environment!
mv install_php.php.disabled install_php.php
mv install-composer.php.disabled install-composer.php

# Run your installation
# ...

# IMMEDIATELY delete after use:
rm install_php.php
rm install-composer.php
```

### Best Practice

Use command-line tools directly instead:

```bash
# Install Composer dependencies
composer install --no-dev --optimize-autoloader

# Check PHP extensions
php -m
```

**DO NOT re-enable these scripts on production servers!**
