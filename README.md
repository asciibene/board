# Retro Bulletin Board

A small PHP and MySQL bulletin board. Visitors can browse threads; registered users can create threads.

## Setup

1. Create a MySQL database and configure these environment variables for the PHP process:
   - `DB_HOST` — database hostname
   - `DB_NAME` — database name
   - `DB_USER` — database username
   - `DB_PASS` — database password (an empty value is allowed if your local database is configured that way)
2. Import `schema.sql` into the database. For example: `mysql -u YOUR_DB_USER -p < schema.sql` (the script creates and selects the `retro_forum` database).
3. Serve the repository with PHP and open `index.php`. The account page is `login.php`.

For an existing installation, run the `CREATE TABLE users` statement from `schema.sql` against the application's database before deploying this version; `CREATE TABLE IF NOT EXISTS` will not add the table to an already-created database automatically.

Set database credentials in your hosting provider's environment configuration, not in source control. The previously committed database password should be rotated immediately; removing it from the current file does not erase it from Git history.
