# Borrow / Return API

## Setup
- Make sure MySQL is running and the database configured in [config.php](config.php).
- The API will auto-create the required tables and a default admin user:
  - email: admin@example.com
  - password: admin123

## Endpoints

### Auth
- POST /api/auth/login
  - Body: { "email": "admin@example.com", "password": "admin123" }
- POST /api/auth/register
  - Body: { "full_name": "Jane Doe", "email": "jane@example.com", "password": "secret123", "role": "borrower" }

### Borrowing
- POST /api/borrow/request
  - Requires bearer token for borrower role.
  - Body: { "item_id": 1, "due_days": 7 }
- PUT /api/borrow/approve/:id
  - Requires bearer token for admin role.
- PUT /api/borrow/return/:id
  - Requires bearer token for admin or borrower role.

### Items
- GET /api/items/available
  - Requires bearer token.
- GET /api/admin/overdue
  - Requires bearer token for admin role.

## Notes
- Late fees are calculated at $2/day after the due date.
- Items must be in `available` status to be requested.
