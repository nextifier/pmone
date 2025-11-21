# Database Import Guide

## PostgreSQL Sequence Issue After Import

### Problem

When importing a PostgreSQL database dump (via SQL file, TablePlus, or other tools), the auto-increment sequences often don't get updated correctly. This causes "duplicate key violation" errors when trying to insert new records.

**Common Error:**
```
SQLSTATE[23505]: Unique violation: 7 ERROR: duplicate key value
violates unique constraint "users_pkey"
```

This happens because:
- The SQL dump contains data with specific IDs (e.g., users with ID 1-13)
- But the sequence counter might still be at 5
- When inserting a new record, PostgreSQL tries to use ID 6 (from sequence)
- But ID 6 already exists in the table → Error!

### Solution

We've created an Artisan command to automatically fix all sequences in your database.

#### Quick Fix (Run after every import)

```bash
php artisan db:fix-sequences
```

This command will:
- Check all tables in your database
- Find sequences that are out of sync
- Automatically fix them to the correct value

#### Check Without Making Changes

To see which sequences would be fixed without actually fixing them:

```bash
php artisan db:fix-sequences --dry-run
```

#### Fix Specific Table Only

To fix only a specific table:

```bash
php artisan db:fix-sequences --table=users
```

### Recommended Workflow

**After importing a database dump:**

1. Import your SQL dump using TablePlus, pgAdmin, or command line
2. Run the fix command:
   ```bash
   php artisan db:fix-sequences
   ```
3. Verify all is fixed (should show "Sequences fixed: 0"):
   ```bash
   php artisan db:fix-sequences --dry-run
   ```

### Example Output

```bash
$ php artisan db:fix-sequences

Checking PostgreSQL sequences...

⚠️  Table 'users': Sequence out of sync
    Current sequence: 5
    Max ID in table:  13
    Next ID will be:  14
✓  Fixed sequence for 'users'

⚠️  Table 'posts': Sequence out of sync
    Current sequence: 1
    Max ID in table:  245
    Next ID will be:  246
✓  Fixed sequence for 'posts'

Summary:
  Tables checked: 37
  Sequences fixed: 2
```

### Technical Details

The command uses this PostgreSQL query to fix sequences:

```sql
SELECT setval('table_name_id_seq', COALESCE((SELECT MAX(id) FROM table_name), 1))
```

This sets the sequence to the maximum ID currently in the table, ensuring the next insert will use a unique ID.

### Prevention

Unfortunately, this is a known issue with PostgreSQL dumps. The sequences are not automatically updated when data is imported with specific IDs. You'll need to run the fix command after each import.

However, you can add this to your deployment/import scripts to automate it:

```bash
# Import database
psql -U username -d database < dump.sql

# Fix sequences
php artisan db:fix-sequences

# Run migrations if needed
php artisan migrate

# Clear cache
php artisan optimize:clear
```

## Other Database Tips

### Importing from Production to Local

1. **Create dump on production:**
   ```bash
   pg_dump -U username -d database -F p -f dump.sql
   ```

2. **Import to local:**
   ```bash
   psql -U username -d local_database < dump.sql
   ```

3. **Fix sequences:**
   ```bash
   php artisan db:fix-sequences
   ```

### Using TablePlus

1. Connect to production database
2. Export → SQL Dump
3. Connect to local database
4. Import SQL file
5. Run: `php artisan db:fix-sequences`

## Troubleshooting

### Command not found

Make sure you're in the project root directory and run:

```bash
php artisan list
```

You should see `db:fix-sequences` in the list.

### Permission denied

Make sure your database user has the USAGE privilege on sequences:

```sql
GRANT USAGE ON ALL SEQUENCES IN SCHEMA public TO your_user;
```

### Still getting errors after fix

1. Clear application cache:
   ```bash
   php artisan optimize:clear
   ```

2. Check if the specific table was fixed:
   ```bash
   php artisan db:fix-sequences --table=users --dry-run
   ```

3. Manually check the sequence:
   ```sql
   SELECT last_value FROM users_id_seq;
   SELECT MAX(id) FROM users;
   ```
