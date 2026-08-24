# CHAPTER 3

# TECHNICAL BACKGROUND


This chapter presents the technical foundation underpinning the development of the BSIS Event Attendance Monitoring System with QR Code Scanning and GPS Verification. It discusses the software and hardware requirements, the programming languages and development tools employed, the database technologies utilized, the network and software architecture adopted, the security features implemented, and the overall system architecture that governs the interaction of all system components.


## 3.1 Software Requirements

The development and deployment of the BSIS Event Attendance Monitoring System require the following software components installed and configured on both the development machine and the production server environment.

**Server-Side Software Requirements:**

| Software Component | Version / Specification | Purpose |
|---|---|---|
| Operating System | Windows 10/11 or Ubuntu 22.04+ | Host operating system for the development and deployment server |
| PHP | 8.2 or higher | Server-side scripting language for the Laravel backend |
| Composer | 2.x | Dependency manager for PHP packages and libraries |
| MySQL | 8.0 or higher | Relational database management system for data persistence |
| Apache / Nginx | Latest stable release | Web server for serving the Laravel application |
| Node.js | 18.x or higher | JavaScript runtime for building front-end assets via Vite |
| npm | 9.x or higher | Package manager for JavaScript dependencies |
| Git | Latest stable release | Version control system for source code management |
| XAMPP / Laragon | Latest stable release | Local development environment bundling Apache, MySQL, and PHP |

**Client-Side Software Requirements (Mobile Application):**

| Software Component | Version / Specification | Purpose |
|---|---|---|
| Android OS | Android 6.0 (API Level 23) or higher | Minimum Android version for the mobile application |
| iOS | iOS 13.0 or higher | Minimum iOS version for Apple device compatibility |
| Expo Go Application | Latest stable release | Development preview client for React Native / Expo apps |
| Google Play Services | Latest version | Required for GPS location services on Android devices |

**Browser Requirements (Admin Web Portal):**

| Browser | Version | Purpose |
|---|---|---|
| Google Chrome | 90+ | Recommended browser for the admin/staff web dashboard |
| Mozilla Firefox | 88+ | Alternative supported browser |
| Microsoft Edge | 90+ | Alternative supported browser |
| Safari | 14+ | Browser support for macOS and iOS web access |


## 3.2 Hardware Requirements

The system requires the following minimum hardware specifications for both the server machine and the end-user mobile devices.

**Server / Development Machine Hardware:**

| Component | Minimum Specification | Recommended Specification |
|---|---|---|
| Processor | Intel Core i3 / AMD Ryzen 3 | Intel Core i5 / AMD Ryzen 5 or higher |
| RAM | 4 GB | 8 GB or higher |
| Storage | 50 GB HDD | 128 GB SSD or higher |
| Network | Stable internet connection (5 Mbps) | Broadband connection (25 Mbps or higher) |
| Display | 1366 x 768 resolution | 1920 x 1080 resolution or higher |

**Student Mobile Device Hardware:**

| Component | Minimum Specification |
|---|---|
| Processor | Quad-core 1.4 GHz or equivalent |
| RAM | 2 GB |
| Storage | 100 MB free space for the application |
| Camera | Rear-facing camera with autofocus capability |
| GPS | Built-in GPS receiver with A-GPS support |
| Network | Wi-Fi (802.11 b/g/n) or Mobile Data (3G/4G/5G) |
| Display | 4.5-inch screen or larger |

**Admin / Staff Machine Hardware (for QR Code Projection):**

| Component | Minimum Specification |
|---|---|
| Laptop / Desktop | Any machine capable of running a modern web browser |
| Display / Projector | External monitor or LCD projector for displaying the live QR code |
| Network | Stable LAN or Wi-Fi connection to the server |


## 3.3 Programming Languages

The following programming languages were utilized in the development of the BSIS Event Attendance Monitoring System:

**PHP (Hypertext Preprocessor) — Version 8.2.** PHP served as the primary server-side scripting language for the backend application. It was used within the Laravel framework to build the RESTful API endpoints, handle business logic for attendance validation, process GPS coordinate calculations, manage authentication workflows, and execute database operations. PHP 8.2 was specifically chosen for its performance improvements, type safety enhancements, and modern language features such as enums, readonly properties, and named arguments.

**JavaScript (ECMAScript 2021+).** JavaScript was employed across both the front-end web portal and the mobile application. On the web side, vanilla JavaScript powered the administrative dashboard interface, including interactive map rendering with Leaflet.js, dynamic QR code generation and auto-refresh, real-time attendance live feeds, and responsive data table interactions. On the mobile side, JavaScript was used through React Native to build the cross-platform student mobile application with features including camera-based QR scanning, GPS location acquisition, and secure local data storage.

**SQL (Structured Query Language).** SQL was used for defining the database schema, creating migration scripts, writing query logic for attendance reports, and performing database backup and restoration operations through the system's built-in backup management module.

**JSX (JavaScript XML).** JSX was used within the React Native mobile application as a syntax extension to JavaScript, enabling the declarative composition of user interface components for the student-facing mobile screens including the Scanner, Dashboard, History, Fines, and Profile screens.

**HTML5 (HyperText Markup Language 5).** HTML5 was used to structure the administrative web portal interface, including the dashboard, event management modals, student management panels, attendance reports, and the student onboarding web pages rendered through Laravel Blade templates.

**CSS3 (Cascading Style Sheets 3).** CSS3 was used for styling the web-based administrative interface, implementing responsive layouts, custom design tokens, glassmorphism effects, animations, and the modern visual design system across the admin and staff portal.


## 3.4 Development Tools

The following development tools, frameworks, and libraries were instrumental in building the system:

**Laravel Framework — Version 12.x.** Laravel is an open-source PHP web application framework following the Model-View-Controller (MVC) architectural pattern. It was selected as the primary backend framework due to its elegant syntax, built-in support for database migrations, Eloquent ORM for object-relational mapping, Artisan CLI for development automation, and a rich ecosystem of packages. Laravel provided the structural foundation for all API routes, controllers, middleware, form request validation, and service layer implementations.

**Laravel Sanctum — Version 4.x.** Laravel Sanctum is a lightweight authentication package that provides API token-based authentication for single-page applications (SPAs) and mobile applications. It was used to issue personal access tokens (Bearer tokens) upon successful student and admin login, securing all authenticated API endpoints against unauthorized access.

**React Native — Version 0.86.x.** React Native is a cross-platform mobile application framework developed by Meta (Facebook) that enables building native mobile applications using JavaScript and React. It was chosen to develop the student-facing mobile application, allowing a single codebase to target both Android and iOS platforms with native performance and access to device hardware including the camera and GPS.

**Expo SDK — Version 57.x.** Expo is a managed development platform built on top of React Native that simplifies the build, deployment, and distribution process. The Expo SDK provided pre-built native modules for camera access (expo-camera), GPS location services (expo-location), secure encrypted storage (expo-secure-store), haptic feedback (expo-haptics), biometric authentication (expo-local-authentication), and device information retrieval (expo-device).

**Vite — Version 6.x.** Vite is a modern front-end build tool that provides fast hot module replacement (HMR) during development and optimized production builds. It was used alongside the Laravel Vite Plugin to compile and bundle the CSS and JavaScript assets for the administrative web portal.

**Bootstrap — Version 5.3.x.** Bootstrap is a front-end CSS framework that provides responsive grid layouts, pre-designed UI components, and utility classes. It was used as the base styling framework for the admin web dashboard, supplemented with custom CSS design tokens and variables for the BSIS-branded visual identity.

**Leaflet.js — Version 1.9.x.** Leaflet is an open-source JavaScript library for interactive, mobile-friendly maps. It was integrated into the admin event creation and editing modals to provide an interactive map picker that allows administrators to visually select event venue coordinates, set the geofence radius, and preview the allowed scanning perimeter on a real map powered by OpenStreetMap tiles.

**Bootstrap Icons — Version 1.11.x.** Bootstrap Icons is an open-source icon library with over 2,000 SVG icons. It was used throughout the web interface for consistent, scalable iconography in navigation menus, buttons, status badges, and form labels.

**Axios — Version 1.19.x.** Axios is a promise-based HTTP client for JavaScript. It was used in the React Native mobile application to make API requests to the Laravel backend, handling authentication token injection, request/response interceptors, and error handling.

**React Navigation — Version 7.x.** React Navigation is the standard navigation library for React Native applications. It was used to implement the mobile app's navigation structure, including a bottom tab navigator for the main screens (Dashboard, Scanner, History, Fines, Profile) and a native stack navigator for the login and event detail screens.

**Visual Studio Code.** Visual Studio Code (VS Code) served as the primary integrated development environment (IDE) for writing, editing, and debugging all source code across the PHP backend, JavaScript frontend, and React Native mobile application.

**Postman.** Postman was used during development for API testing, sending HTTP requests to backend endpoints, validating response payloads, and debugging authentication flows.

**XAMPP.** XAMPP provided the local development environment bundling Apache HTTP Server, MySQL database server, and PHP interpreter, enabling the Laravel application to run locally during the development phase.

**Cloudflare Tunnel (cloudflared).** Cloudflare Tunnel was used to expose the locally hosted Laravel development server to the public internet via a secure tunnel, enabling the mobile application running on physical devices to communicate with the backend API during development and testing without requiring a dedicated hosting server.


## 3.5 Database Technologies

**MySQL — Version 8.0.** MySQL is an open-source relational database management system (RDBMS) that uses Structured Query Language (SQL) for data definition, manipulation, and querying. MySQL was selected as the database engine for the BSIS Event Attendance Monitoring System due to its reliability, performance, wide community support, and seamless integration with the Laravel framework through the Eloquent ORM.

The database named tpc_attendance contains the following principal tables:

| Table Name | Purpose |
|---|---|
| users | Stores all user accounts including students, event staff, and administrators with role-based differentiation, year level, and section/block information |
| events | Stores event sessions with title, description, date/time schedules, venue GPS coordinates, geofence radius, session type (half-day/whole-day), attendance scanning windows, fine amounts, target year level restrictions, and bypass controls |
| attendance | Records individual student attendance scans with timestamps for check-in/check-out (half-day) and AM In/AM Out/PM In/PM Out (whole-day), GPS coordinates, distance from venue, device credentials, attendance status, slot-level statuses, and fine calculations |
| devices | Manages student device binding records with unique device credentials (UUIDs), device names, user agents, IP addresses, and binding status for the one-student-one-device security model |
| device_reset_requests | Tracks student-initiated device reset requests with approval/rejection workflow managed by administrators |
| onboarding_tokens | Stores single-use, time-limited (48-hour) secure onboarding tokens generated during student provisioning for password creation and account activation |
| event_staff | Pivot table associating event staff users with specific events they are authorized to manage |
| attendance_sync_records | Logs offline attendance batch synchronization transactions from the mobile app |
| audit_logs | Comprehensive audit trail recording all system actions, security events, login attempts, and administrative operations with IP addresses, user agents, and metadata |
| system_settings | Key-value configuration store for dynamic system settings such as QR expiration duration |
| personal_access_tokens | Laravel Sanctum token storage for API authentication sessions |
| password_reset_tokens | Stores password reset tokens for the forgot password workflow |
| sessions | Server-side session storage for web-based administrative sessions |
| cache | Database-backed cache storage for Laravel's caching subsystem |
| jobs | Queue job storage for background task processing |

The database schema was built incrementally using Laravel's migration system, with 22 migration files tracking the evolution from the initial schema to the final production schema.


## 3.6 Network Architecture

The BSIS Event Attendance Monitoring System operates on a client-server network architecture where all communication between clients (mobile app and web browser) and the server occurs over HTTPS (Hypertext Transfer Protocol Secure).

**Production Network Topology:**

The system is accessible at the domain https://tpc-bsis.online, configured through Cloudflare Tunnel to securely expose the locally hosted Laravel server to the internet. The network flow follows this path:

1. Student Mobile App to Internet to Cloudflare Edge to Cloudflare Tunnel to Local Server (Laravel API). The React Native mobile application sends API requests over HTTPS to the production domain. Cloudflare's edge network handles DNS resolution, SSL/TLS termination, and DDoS protection before forwarding the request through an encrypted tunnel to the local development server running the Laravel application.

2. Admin Web Browser to Internet to Cloudflare Edge to Cloudflare Tunnel to Local Server (Laravel Web + API). The administrator or event staff accesses the web dashboard through a modern web browser. The request follows the same Cloudflare Tunnel path to reach the Laravel application, which serves both the Blade-rendered HTML pages and the RESTful API endpoints.

3. Server to MySQL Database (localhost:3306). All database operations occur on the same server machine via a local TCP connection to the MySQL database server, eliminating network latency for database queries.

4. Server to SMTP (smtp.gmail.com:587). The system sends transactional emails (onboarding invitations, password reset links) through Gmail's SMTP server using TLS encryption.

**Communication Protocol:**

All API communication uses JSON (JavaScript Object Notation) as the data interchange format. Requests are authenticated using Bearer tokens issued by Laravel Sanctum, transmitted in the Authorization HTTP header. The system enforces HTTPS for all production traffic to ensure data confidentiality and integrity during transmission.


## 3.7 Software Architecture

The BSIS Event Attendance Monitoring System follows a multi-layered software architecture combining the Model-View-Controller (MVC) pattern with a RESTful API-driven client-server architecture.

**Model-View-Controller (MVC) Pattern:**

The Laravel backend implements the MVC pattern as follows:

Model Layer. Eloquent ORM models (User, Event, Attendance, Device, AuditLog, OnboardingToken, SystemSetting, AttendanceSyncRecord, DeviceResetRequest) encapsulate database entities, define relationships (HasMany, BelongsTo, BelongsToMany), attribute casting, computed accessors, and business logic methods such as isEligibleStudent(), getActiveWindowStatus(), and getFineBreakdownAttribute().

View Layer. Laravel Blade templates (admin.blade.php, student.blade.php) render the server-side HTML for the web-based interfaces. The mobile application's view layer is composed of React Native screen components (LoginScreen, DashboardScreen, ScannerScreen, HistoryScreen, FinesScreen, ProfileScreen, EventDetailsScreen).

Controller Layer. API controllers in the App\Http\Controllers\Api namespace handle HTTP requests, orchestrate service layer calls, perform authorization checks, and return standardized JSON responses. The system comprises 19 API controllers covering authentication, attendance, events, fines, reports, dashboard, user management, device management, backups, and system settings.

**Service Layer Architecture:**

Business logic is encapsulated in dedicated service classes to promote separation of concerns:

QrTokenService. Handles the generation and cryptographic validation of dynamic QR tokens using HMAC-SHA256 digital signatures with configurable expiration windows.

GpsValidationService. Implements the Haversine formula for calculating great-circle distances between GPS coordinates and performs geofence radius validation and anti-spoofing teleportation detection.

AbsenceProcessorService. Automates the processing of student absences upon event conclusion, generating fine records for non-attendees and reconciling partially attended students with slot-level penalty calculations.

**RESTful API Architecture:**

The backend exposes a comprehensive RESTful API with 45+ endpoints organized under the /api prefix. The API follows REST conventions with proper HTTP method usage (GET for retrieval, POST for creation, PUT for updates, DELETE for removal) and standard HTTP status codes for response semantics.

**Three-Tier Architecture:**

At the highest level, the system follows a three-tier architecture:

1. Presentation Tier. The React Native mobile application and the web-based admin dashboard.
2. Application / Logic Tier. The Laravel PHP backend with controllers, services, middleware, and form request validators.
3. Data Tier. The MySQL relational database storing all persistent data.


## 3.8 Security Features

The BSIS Event Attendance Monitoring System implements multiple layers of security to protect user data, prevent attendance fraud, and ensure system integrity.

**Authentication and Token-Based Access Control.** The system uses Laravel Sanctum to issue personal access tokens (Bearer tokens) upon successful login. All authenticated API endpoints require a valid token in the Authorization header. Tokens are stored securely on the mobile device using Expo Secure Store, which leverages the device's hardware-backed keychain (iOS Keychain / Android Keystore).

**Password Hashing with Bcrypt.** All user passwords are hashed using the Bcrypt algorithm with 12 rounds of salting before storage. The system never stores, transmits, or logs plaintext passwords. Password verification uses PHP's password_verify() function with timing-safe comparison to prevent timing attacks.

**Role-Based Access Control (RBAC).** The system enforces three hierarchical roles — admin, event_staff, and student — with a custom CheckRole middleware that validates both the user's role and active account status before granting access. Administrative endpoints (event creation, student provisioning, database backups, user management) are restricted to the admin role exclusively.

**One-Student-One-Device Binding.** Each student account is permanently bound to a single mobile device upon first login. The binding is enforced through a unique device credential (UUID v4) that is cryptographically verified on every subsequent login and attendance scan. Attempts to log in from a secondary device are blocked, and after three unauthorized device attempts, the student account is automatically suspended to prevent proxy attendance.

**Anti-App Duplication and Cloning Detection.** The system detects when two different student accounts attempt to authenticate from the same physical device (indicating app cloning or dual-space exploitation). Such attempts are blocked immediately and logged as security violations in the audit trail.

**Dynamic QR Code with HMAC-SHA256 Digital Signatures.** Event QR codes are dynamically generated every 20 seconds (configurable) with cryptographic HMAC-SHA256 signatures computed using the application's secret key. Each QR token embeds the event ID, generation timestamp, expiration timestamp, a random nonce, and the digital signature. On scan, the server validates the signature integrity and expiration to prevent QR code screenshots, photocopying, or sharing.

**GPS Geofence Radius Validation (Haversine Formula).** The system computes the great-circle distance between the student's GPS coordinates and the event venue coordinates using the Haversine formula. Students located outside the administrator-configured geofence radius (default: 50 meters) are denied attendance recording.

**Anti-Spoofing Teleportation Detection (Fake GPS Prevention).** The system implements an anti-teleportation algorithm that compares a student's current GPS coordinates against their most recent scan location within a 45-minute window. If the system detects an impossible location jump (more than 100 meters in under 15 seconds, or travel exceeding 60 km/h), the scan is rejected as a potential mock location or fake GPS attempt.

**Login Rate Limiting and Brute-Force Protection.** The authentication system enforces a maximum of three failed password attempts per user per minute. After exceeding this threshold, the account is temporarily locked for 60 seconds. This prevents brute-force password attacks while minimizing inconvenience for legitimate users who mistype their password.

**Comprehensive Audit Logging.** Every significant system action is recorded in the audit_logs table with the actor's user ID, action type, human-readable description, client IP address, user agent string, and structured metadata. Logged events include successful and failed logins, attendance scans, account provisioning, device resets, fine payments, event operations, and security violations.

**HTTPS/TLS Encryption in Transit.** All network communication between clients and the server is encrypted using HTTPS with TLS certificates managed by Cloudflare, ensuring confidentiality and integrity of data in transit including authentication tokens, GPS coordinates, and personal student information.

**Secure Onboarding with Single-Use Tokens.** Student account activation uses a secure onboarding workflow where the administrator provisions the student and the system generates a cryptographically random 64-character token with a 48-hour expiration. The student uses this one-time token to set their password and activate their account. Once used, the token is permanently invalidated.

**CSRF Protection and Input Validation.** All web-based forms include Cross-Site Request Forgery (CSRF) token protection. API endpoints use Laravel Form Request classes with comprehensive validation rules to sanitize and validate all incoming data, preventing SQL injection, cross-site scripting (XSS), and other injection attacks.


## 3.9 System Architecture

The overall system architecture of the BSIS Event Attendance Monitoring System illustrates the interaction between the three primary system components: the Student Mobile Application, the Admin/Staff Web Dashboard, and the Laravel Backend Server with its MySQL database.

**System Architecture Diagram Description:**

The system architecture follows a centralized client-server model where:

1. Student Mobile Application (React Native / Expo) — Installed on each student's personal mobile device. It communicates with the backend server over HTTPS RESTful API calls. The mobile app accesses two key hardware peripherals: the device camera for QR code scanning and the GPS receiver for location verification. Authentication tokens are stored in the device's encrypted secure storage.

2. Admin/Staff Web Dashboard (Laravel Blade + JavaScript) — Accessed through a web browser on laptops or desktops. It communicates with the same backend API for data retrieval and management operations. The dashboard renders interactive maps (Leaflet.js), generates and auto-refreshes dynamic QR codes, and displays real-time attendance statistics.

3. Laravel Backend API Server (PHP 8.2 + Laravel 12) — The central application server that processes all business logic. It receives API requests from both the mobile app and the web dashboard, validates authentication tokens via Sanctum middleware, executes the attendance validation pipeline (QR verification, device binding check, GPS geofence validation, anti-spoofing check, time window validation, attendance recording), and returns JSON responses.

4. MySQL Database Server — Stores all persistent data including user accounts, events, attendance records, device bindings, audit logs, and system settings. Connected to the Laravel application via the Eloquent ORM over a local TCP connection.

5. External Services — Gmail SMTP for transactional email delivery (onboarding invitations, password resets) and Cloudflare for DNS, SSL/TLS termination, DDoS protection, and secure tunneling.

**Attendance Scanning Pipeline (Sequential Validation Chain):**

When a student scans a QR code, the system executes the following validation steps in strict sequence:

Step 1: Validate Student Account Status (must be 'active')
Step 2: Validate Dynamic QR Token (HMAC-SHA256 signature + expiration)
Step 3: Validate Device Binding (device credential must match registered device)
Step 4: Validate Event Status (must be 'active')
Step 4.2: Validate Target Year Level Eligibility
Step 4.5: Validate Attendance Time Window (or emergency bypass)
Step 5: Anti-Spoofing Teleportation Check (fake GPS detection)
Step 5.1: GPS Geofence Radius Validation (Haversine formula)
Step 6: Record Attendance (determine slot, calculate lateness/fines)

If any validation step fails, the pipeline immediately returns an error response with a descriptive message, and the failed attempt is logged in the audit trail for administrative review. Only when all eight validation steps pass is the attendance record successfully created or updated.
