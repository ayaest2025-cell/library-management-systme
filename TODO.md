# Books Module Upgrade - TODO

## Steps

- [x] 1. Analyze current codebase
- [x] 2. Create plan and get approval

### Implementation

- [x] 3. Update `api/db.php` - Update PDO schema for books table with all new fields
- [x] 4. Update `api/routes.php` - Update books GET to return all fields, books/add POST to accept all fields
- [x] 5. Update `api/helpers.php` - Add uploadBookCover/deleteBookCover helper functions for API
- [x] 6. Update `api/database_updates.sql` - Update SQL schema for books table

### Verification

- [x] 7. All files updated and consistent

