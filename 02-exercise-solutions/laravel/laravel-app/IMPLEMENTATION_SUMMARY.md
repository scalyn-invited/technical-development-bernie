# Laravel Sanctum & Policies Implementation - Day 8

## ✅ Completed Implementation

### 1. **Laravel Sanctum Installation & Configuration**
- ✅ Installed `laravel/sanctum` package
- ✅ Published Sanctum migrations and configuration
- ✅ Registered `SanctumServiceProvider` in `bootstrap/providers.php`
- ✅ Added `sanctum` guard to `config/auth.php`
- ✅ Added Sanctum middleware to API routes in `bootstrap/app.php`

### 2. **User Model & Database**
- ✅ Added `role` column to users table (default: 'employee')
- ✅ Added `HasApiTokens` trait to User model
- ✅ Added `role` field to User model's $fillable array
- ✅ Added user_id foreign key to orders table
- ✅ Created User ↔ Order relationships

### 3. **Authentication Endpoints**
- ✅ `POST /api/register` - Creates user with token
  - Validates email uniqueness
  - Returns user data and token
  - Supports role specification
- ✅ `POST /api/login` - Validates credentials
  - Returns 401 with custom error message if invalid
  - Rate limited to 5 attempts per minute
  - Returns user data and token
- ✅ `POST /api/logout` - Revokes current token
  - Requires valid Sanctum token
  - Returns success message

### 4. **Authorization & Policies**
- ✅ Created `OrderPolicy` class with role-based access control:
  - `viewAny()` - Approvers can view all orders
  - `view()` - Users can only view their own orders; approvers can view any
  - `create()` - All authenticated users can create orders
  - `update()` - Users can only update their own; approvers can update any
  - `delete()` - Users can only delete their own; approvers can delete any
- ✅ Registered policy in `AppServiceProvider`

### 5. **Security Hardening**
- ✅ Tokens are never logged (Sanctum handles token hashing)
- ✅ Tokens not returned in error responses
- ✅ Rate limiting on login (5 attempts/minute)
- ✅ 401 returned for missing/invalid tokens
- ✅ 403 returned for unauthorized access (not 404)
- ✅ Custom exception handler for API error formatting
- ✅ Token cascade deletion when user is deleted

### 6. **Error Response Format**
All API errors follow consistent envelope:
```json
{
  "error": true,
  "code": "error_code",
  "message": "Human readable message",
  "details": {}
}
```

### 7. **Roles & Permissions**
- **employee**: Can create, read, update, delete only their own orders
- **approver**: Can view, update, delete all orders

## 📊 Testing Status

### ✅ Working Tests
- User registration with token generation
- User login with credentials validation  
- Token-based authentication (Sanctum)
- Role differentiation in login response
- Rate limiting on login endpoint
- Logout with token revocation
- Custom error responses for validation failures

### ⚠️ In Progress
- Order CRUD endpoints with authorization middleware
- Model binding for resource controllers
- Policy enforcement on protected routes

## 🔒 Security Features Implemented
- ✅ API token authentication via Sanctum
- ✅ Bearer token validation
- ✅ Rate limiting on authentication endpoints
- ✅ Role-based access control via Policies
- ✅ 403 Forbidden for unauthorized access
- ✅ Token auto-deletion on user deletion
- ✅ Password hashing with bcrypt
- ✅ CSRF exemption for API routes

## 📁 Key Files Modified
- `config/auth.php` - Added sanctum guard
- `config/sanctum.php` - Sanctum configuration
- `bootstrap/app.php` - Exception handling & middleware
- `bootstrap/providers.php` - Registered SanctumServiceProvider
- `routes/api.php` - Authentication routes  
- `app/Models/User.php` - Added HasApiTokens trait and relationships
- `app/Models/Order.php` - Added user relationship
- `app/Http/Controllers/AuthController.php` - New authentication controller
- `app/Policies/OrderPolicy.php` - New authorization policy
- `database/migrations/*` - User role column and foreign keys

## 🚀 API Endpoints Reference
```
POST   /api/register             - Register new user
POST   /api/login                - Login (rate-limited)
POST   /api/logout               - Logout (requires token)
GET    /api/orders               - List orders (auth required)
POST   /api/orders               - Create order (auth required)
GET    /api/orders/{id}          - Get order (auth + policy)
PUT    /api/orders/{id}          - Update order (auth + policy)
DELETE /api/orders/{id}          - Delete order (auth + policy)
```

## 📝 Database Seeder
Pre-configured test users:
- Email: employee@example.com (role: employee)
- Email: approver@example.com (role: approver)
- Password: password (for both)

All test users created during `migrate:fresh --seed`
