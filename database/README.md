# Database Migration System

This system helps you and your team keep the database synchronized across all development environments.

## How It Works

Instead of manually sharing SQL dumps, all database changes are stored as migration files in the `migrations/` folder. When a teammate pulls the latest code, they simply run the migration script to update their database automatically.

## Quick Start

### First Time Setup

1. **Via Web Browser:**
   - Visit: `http://localhost/mywebsite/database/migrate.php`
   - Click "Run Migrations"

2. **Via Command Line:**
   ```bash
   cd c:\xampp\htdocs\mywebsite
   php database/migrate.php
   ```

### Daily Workflow

When a teammate makes database changes:

1. **They create a new migration file** in `database/migrations/`:
   ```
   002_add_new_feature.sql
   003_modify_users_table.sql
   ```

2. **You pull their changes** from Git

3. **You run migrations**:
   ```bash
   php database/migrate.php
   ```

That's it! Your database is now up to date.

## Migration File Naming

Name your migration files with:
- A number prefix (001, 002, 003...)
- An underscore
- A descriptive name
- .sql extension

Examples:
- `001_initial_schema.sql`
- `002_add_user_roles.sql`
- `003_create_notifications_table.sql`

## Commands

### Run All Pending Migrations
```bash
php database/migrate.php
```
or visit: `http://localhost/mywebsite/database/migrate.php`

### Check Migration Status
```bash
php database/migrate.php status
```
or visit: `http://localhost/mywebsite/database/migrate.php?action=status`

## Creating New Migrations

When you need to make database changes:

1. Create a new file in `database/migrations/` folder
2. Name it with the next number: `00X_description.sql`
3. Write your SQL statements
4. Test it by running migrations
5. Commit the file to Git

Example migration file:
```sql
-- Migration: Add notifications table
-- Created: 2025-12-18
-- Description: Create table for user notifications

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` 
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
```

## Benefits

✅ **No more manual SQL sharing** - Just pull and run migrations
✅ **Version controlled** - All database changes are tracked in Git
✅ **Team synchronized** - Everyone has the same database structure
✅ **Easy rollback** - You can see what changed and when
✅ **Automatic tracking** - System knows what's been applied

## Troubleshooting

### "Migration already executed"
This is normal - the system skips already-run migrations.

### "Error in migration"
- Check the SQL syntax in the migration file
- Ensure table names and columns are correct
- Run migrations one at a time to identify the problem

### "Can't connect to database"
- Make sure XAMPP MySQL is running
- Check `config/database.php` settings
- Verify database name exists

## Git Workflow

```bash
# Teammate adds a migration
git add database/migrations/004_new_feature.sql
git commit -m "Add new feature migration"
git push

# You sync your database
git pull
php database/migrate.php
```

## Important Notes

- ⚠️ **Never edit existing migration files** that have been committed
- ⚠️ **Always create new migrations** for changes
- ⚠️ **Test migrations locally** before committing
- ✅ **Commit migration files** to Git
- ✅ **Run migrations** after pulling changes

## Default Admin Account

After running initial migration:
- Username: `admin`
- Password: `admin123`
- Email: `admin@classconnect.com`
