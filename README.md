# FindRival

FindRival is a comprehensive web application designed for sports enthusiasts to organize and discover local matches and athletic events. The platform facilitates community building by allowing users to create events, manage participation, and connect with other players in their area.

### Core Functionalities

*   **Authentication & Security**: Secure user registration and login system with role-based access control (User/Admin).
*   **Event Management**: 
    *   **Creation**: Users can create new sporting events, specifying location, time, sport type, difficulty level, and player limits.
    *   **Editing**: Event owners can modify event details to reflect changes in plans.
    *   **Participation**: One-click joining and leaving of events with real-time participant tracking and transactional integrity.
*   **Discovery & Search**:
    *   **Local Search**: Find events in your immediate vicinity using geographic radius filtering.
    *   **Sport Filters**: Filter matches by sport type and skill level to find the perfect game.
    *   **Interactive Maps**: Visualize event locations and navigate through local matches using an integrated map interface (Leaflet).
*   **Personal Tracking**:
    *   **Participation History**: Keep track of events you've participated in and upcoming matches.
    *   **Profile Management**: Update your personal information, bio, and favorite sports to personalize your experience.

### Administrative Capabilities

Administrators have elevated permissions to ensure platform quality and safety:
*   **User Management**: Full access to the list of accounts with the ability to edit any user's profile, change roles, or disable accounts.
*   **Content Moderation**: Administrators can edit or delete *any* event on the platform, regardless of ownership, to maintain community standards.

---

## Technical Architecture

The application utilizes a layered architecture to ensure separation of concerns and maintainability.

### System Flow

![alt text](image-7.png)

### Database Design (ERD)

![Database Entity-Relationship Diagram](docs/erd_diagram.png)

---

## Application Demo

![alt text](image.png)

![alt text](image-1.png)

### Key Features Walkthrough

![alt text](image-3.png)

![alt text](image-4.png)

![alt text](image-5.png)

![alt text](image-6.png)


### Admin Panel
![alt text](image-2.png)

---

## Security & Reliability Audit

The application implements a comprehensive security suite ("Security Bingo") to ensure professional-grade data protection:

### Data Protection & Validation
- **SQL Injection Protection**: All database queries use Prepared Statements; no manual string concatenation is used in queries.
- **XSS Mitigation**: All user-generated content is strictly escaped before being rendered in HTML views.
- **Server-Side Validation**: Rigorous validation of email formats, input lengths, and password complexity on the server side.
- **Minimal Data Retrieval**: Repositories query only the specific set of data required for the current operation.

### Authentication & Session
- **Secure Password Storage**: Passwords are never stored as plain text. We use `password_hash` with `bcrypt`.
- **Anti-Enumeration**: Generic "Invalid email or password" messages to prevent attackers from verifying the existence of accounts.
- **Session Security**: 
    - Forced session regeneration upon successful login.
    - Security-focused Cookie flags: `HttpOnly`, `SameSite=Lax`.
- **Method Restriction**: Critical actions (Login/Register/Create) accept data ONLY via `POST` requests; `GET` is reserved for rendering views.
- **CSRF Protection**: State-changing forms are protected with CSRF tokens.

### Auditing & Limits
- **Login Auditing**: All failed login attempts are logged (without storing passwords).
- **Rate Limiting**: IP-based blocking and cooldown periods after multiple failed authentication attempts.
- **Audit Logging**: Automated database triggers track all critical lifecycle events (e.g., joining/leaving a match).
- **Production Safety**: Detailed stack traces and raw errors are suppressed in production mode to prevent information leakage.

---

## Test Scenarios (Step-by-Step)

Follow these steps to verify the core functionalities of the application.


### Demo Credentials

| Role | Email | Password |
| :--- | :--- | :--- |
| **Administrator** | `admin@gmail.com` | `adminadmin` |
| **Standard User** | `anna.nowak@example.com` | `test1234` |
| **Standard User** | `kasia.wisniewska@example.com` | `test5678` |

---

### 1. Authentication & Security
*   **Registration**: Navigate to `/register`, create a new account.
    *   *Result*: Automated redirect to `/dashboard`.
*   **Security (Anti-Enumeration)**: Try logging in with a non-existent email.
    *   *Result*: Generic "Invalid email or password" message (does not reveal if email exists).
*   **Session Management**: Log in, then close the tab and reopen.
    *   *Result*: Session persists (Cookie-based). Click "Logout" to destroy the session.

### 2. Role-Based Access (403/401)
*   **Unauthorized Access (401)**: Open `/dashboard` in an incognito window.
    *   *Result*: System redirects to `/login`.
*   **Forbidden Access (403)**: Log in as a **Standard User** and manually type `/accounts` in the URL.
    *   *Result*: Custom **403 Forbidden** page is displayed.
*   **Admin Access**: Log in as `admin@gmail.com`. Navigate to `/accounts`.
    *   *Result*: Full list of users and management tools are visible.

### 3. Event Lifecycle (CRUD)
*   **Create**: From the dashboard, click "Create Event". Pick a location on the map, select "Soccer", and set a limit of 10 players.
*   **Read**: Find your event in the "Sports" tab using filters.
*   **Update**: Open your event details, click "Edit", and change the start time or description.
*   **Delete**: As an owner, click "Delete" on the event page.
    *   *Result*: Event is removed from the database and maps.

### 4. Database Views & Triggers (Technical Verification)
*   **Triggers (Audit)**: Join an event as a user.
    *   *Verification*: Check the `audit_log` table; it should contain a new `INSERT` record for `event_participants`.
*   **Triggers (Stats)**: Check the `user_statistics` table for your `user_id`.
    *   *Verification*: `total_events_joined` should increment automatically via the database trigger.
*   **Views**: Query the `vw_events_full` view.
    *   *Verification*: Data should include formatted owner names and pre-calculated participant counts without manual joins.

---

## Testing

The project includes symbolic tests to demonstrate automation and quality control.

### Unit Tests
The project uses PHPUnit for unit testing. To run them without local installation, use Docker:

1. **Install Dependencies** (only needed once):
   ```bash
   docker-compose exec php composer install
   ```

2. **Run Tests**:
   ```bash
   docker-compose exec php ./vendor/bin/phpunit tests
   ```

### Automated API Tests
A bash script is provided to test core endpoints via `curl`:
```bash
chmod +x tests/api_tests.sh
./tests/api_tests.sh http://localhost:8080
```

### Postman Collection
For manual testing, import the `tests/FindRival.postman_collection.json` file into Postman. It contains pre-configured requests for Login, Registration, and Event Search.


## Getting Started

### Prerequisites
- Docker Engine
- Docker Compose

### Installation and Setup

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd wdpai-2025
   ```

2. **Configuration**
   ```bash
   cp .env.example .env
   ```
   *(Defaults are pre-set for a Docker environment).*

3. **Running the Application**
   ```bash
   docker-compose up -d --build
   ```
   The application will be accessible at `http://localhost:8080`.



---

## Project Implementation Checklist

### Documentation and Architecture
- [x] **DOCUMENTATION IN README.MD** - Complete project documentation with functionality descriptions, architecture, and instructions
- [x] **MVC/FRONT-BACKEND APPLICATION ARCHITECTURE** - Layered architecture with separation of Frontend (HTML/CSS/JS) and Backend (Controllers/Repositories)
- [x] **OBJECT-ORIENTED CODE (BACKEND PART)** - All controllers, repositories, and models written in object-oriented paradigm
- [x] **ERD DIAGRAM** - Entity-relationship diagram included in documentation (Mermaid)
- [x] **GIT** - Project managed by Git version control system
- [x] **PROJECT THEME REALIZATION** - FindRival - platform for organizing local sports events

### Database
- [x] **POSTGRESQL** - PostgreSQL 16 as the main database system
- [x] **DATABASE COMPLEXITY** - Relational database with multiple tables (users, events, event_participants, sports, levels, user_favourite_sports, audit_log, user_statistics)
- [x] **DATABASE EXPORT TO .SQL FILE** - Initialization scripts in `database/init.sql`
- [x] **VIEWS** - View `vw_events_full` for simplified event data retrieval
- [x] **TRIGGERS** - Triggers for automatic auditing (`audit_trigger`) and user statistics updates
- [x] **FUNCTIONS** - Database functions (`audit_log_function`, `update_user_statistics`)
- [x] **TRANSACTIONS** - Transactions for join/leave event operations ensuring data integrity
- [x] **ACTIONS ON REFERENCES** - CASCADE on user and event deletions, SET NULL for appropriate relations

### Frontend Technologies
- [x] **HTML** - Semantic HTML5 with proper structure and SEO
- [x] **JAVASCRIPT** - Vanilla JavaScript for interactivity
- [x] **FETCH API (AJAX)** - Asynchronous communication with backend (joining/leaving events, search)
- [x] **DESIGN** - Modern, premium design with gradients and animations
- [x] **RESPONSIVENESS** - Responsive layout adapted to different devices

### Security and Authorization
- [x] **PHP** - Backend written in PHP 8.1+
- [x] **LOGIN** - Login system with validation and security measures
- [x] **USER SESSION** - Session management with `HttpOnly` cookies and session regeneration
- [x] **USER PERMISSIONS** - Role-based access control system
- [x] **USER ROLES - AT LEAST TWO** - Roles: User (standard user) and Admin (administrator)
- [x] **LOGOUT** - Logout functionality with session destruction
- [x] **SECURITY** - Comprehensive security suite:
  - Prepared Statements (SQL Injection protection)
  - XSS mitigation (user data escaping)
  - Password hashing (bcrypt)
  - CSRF protection
  - Rate limiting (brute-force protection)
  - Anti-enumeration (during login)
  - Audit logging (tracking critical operations)

### Code Quality
- [x] **NO CODE REPLICATION** - Code following DRY principle (Don't Repeat Yourself), Singleton pattern for repositories, reusable components

---

