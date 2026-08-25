# TALIBON POLYTECHNIC COLLEGE
## Bachelor of Science in Information Systems (BSIS)
### Capstone Research Project — Institutional User Manual

---

# SECURE DYNAMIC QR ATTENDANCE SYSTEM WITH GPS GEOFENCING & DEVICE BINDING
## Operational User Manual for Administrators, Event Staff, and Students

```
System Title     : TPC BSIS Attendance System
Target Audience  : Department Administrators, Event Staff / Treasurers, and BSIS Students
Platform Support : Web Browser (Desktop / Laptop) & Progressive Web Application (PWA / Mobile)
Document Version : 4.0 (Production Release)
```

---

## TABLE OF CONTENTS
1. [Introduction & System Overview](#1-introduction--system-overview)
2. [Administrator Operations Guide](#2-administrator-operations-guide)
   - 2.1 [Dashboard Overview & Navigation](#21-dashboard-overview--navigation)
   - 2.2 [Event Creation & Interactive Map Geofencing](#22-event-creation--interactive-map-geofencing)
   - 2.3 [Configuring Dynamic QR Expiration Intervals](#23-configuring-dynamic-qr-expiration-intervals)
   - 2.4 [Broadcasting Live Dynamic QR Code Screen](#24-broadcasting-live-dynamic-qr-code-screen)
   - 2.5 [Finalizing Events & Auto-Processing Absence Fines](#25-finalizing-events--auto-processing-absence-fines)
   - 2.6 [Fine Tracking, Batch Payments, & Waivers](#26-fine-tracking-batch-payments--waivers)
   - 2.7 [Generating Clearance Reports, Printing, & Exporting (Word/CSV)](#27-generating-clearance-reports-printing--exporting-wordcsv)
   - 2.8 [Student Account Provisioning (Single & CSV Batch Import)](#28-student-account-provisioning-single--csv-batch-import)
   - 2.9 [Reviewing Device Reset Requests](#29-reviewing-device-reset-requests)
   - 2.10 [System Audit Logs & Security Accountability](#210-system-audit-logs--security-accountability)
3. [Event Staff & Department Treasurer Guide](#3-event-staff--department-treasurer-guide)
   - 3.1 [Assigned Event Operations](#31-assigned-event-operations)
   - 3.2 [Emergency Window Bypass Activation](#32-emergency-window-bypass-activation)
   - 3.3 [Recording Manual Staff Overrides](#33-recording-manual-staff-overrides)
   - 3.4 [Offline Attendance Syncing During Outages](#34-offline-attendance-syncing-during-outages)
4. [Student User Guide](#4-student-user-guide)
   - 4.1 [Account Onboarding & Password Setup](#41-account-onboarding--password-setup)
   - 4.2 [Single-Device Binding Policy](#42-single-device-binding-policy)
   - 4.3 [In-App Camera Scanning & Location Verification](#43-in-app-camera-scanning--location-verification)
   - 4.4 [Viewing Daily Attendance Progress Timeline](#44-viewing-daily-attendance-progress-timeline)
   - 4.5 [Checking Fine Balances & Clearance Status](#45-checking-fine-balances--clearance-status)
   - 4.6 [Submitting a Device Reset Request](#46-submitting-a-device-reset-request)
5. [Troubleshooting & Frequently Asked Questions (FAQ)](#5-troubleshooting--frequently-asked-questions-faq)

---

# 1. INTRODUCTION & SYSTEM OVERVIEW

The **TPC BSIS Attendance System** is an institutional mobile and web-based attendance tracking platform engineered specifically for the Bachelor of Science in Information Systems department at Talibon Polytechnic College.

The system replaces error-prone paper logbooks with a **three-tier security verification protocol**:
1. **Dynamic Rotating QR Code:** Automatically refreshes every 60 seconds with HMAC-SHA256 digital signature to eliminate screenshot sharing and proxy attendance.
2. **GPS Geofencing:** Verifies student physical presence within the accredited venue boundary (e.g. 50-meter radius) using high-precision location algorithms.
3. **Single-Device Lockdown:** Binds student accounts to their registered smartphone to prevent multiple students from logging into one phone.

---

# 2. ADMINISTRATOR OPERATIONS GUIDE

### 2.1 Dashboard Overview & Navigation
1. Open a modern web browser (Google Chrome, Microsoft Edge, or Firefox) and navigate to:
   `https://tpc-bsis.online/admin`
2. Enter your **Administrator Institutional Email** and **Password**, then click **Sign In**.
3. The Dashboard presents key operational widgets:
   * **Active Session Live Banner:** Displays the ongoing event with one-click access to the QR broadcast screen.
   * **Turnout Metrics & Donut Chart:** Shows real-time counts for Present, Late, Absent, and Manual Overrides.
   * **Calendar & Slot Distribution:** Interactive month calendar showing scheduled event dates.
   * **Upcoming Events Widget:** Displays upcoming events chronologically (closest date at the top).

---

### 2.2 Event Creation & Interactive Map Geofencing
1. Navigate to **Event Management** (`#events`) from the left sidebar and click **`+ Create New Event`**.
2. Complete the event details:
   * **Event Title & Description:** Name and purpose of the activity.
   * **Session Format:** Choose between **Half-Day (2 Scans)** or **Whole-Day (4 Scans: AM In, AM Out, PM In, PM Out)**.
   * **Interactive Map Pinning:** Click anywhere on the interactive Leaflet map to place the venue pin, or drag the marker directly over the campus building (e.g., TPC Gymnasium, Audio-Visual Room, BSIS Computer Lab).
   * **Allowed Radius (Meters):** Set the geofence perimeter (default: `50` meters).
   * **Scanning Timeframe Windows:** Set scheduled timeframes with 12-hour AM/PM selectors. You can click **`Auto-Fill Slots`** to automatically calculate standard institutional timeframes.
   * **Fine Amount Per Slot (PHP):** Set the penalty assessed for each missed or late scanning session (e.g., `₱20.00`).
   * **Target Year Levels:** Choose specific year levels ($1^{\text{st}}, 2^{\text{nd}}, 3^{\text{rd}}, 4^{\text{th}}$ Year) or **All BSIS Students**.
   * **Assign Event Staff:** Select student officers or faculty members authorized to manage the event.
3. Click **`Save Event`** to publish the schedule.

---

### 2.3 Configuring Dynamic QR Expiration Intervals
1. Click your **Profile Avatar / Name** at the top right of the navigation header.
2. Select **`QR Interval Settings`**.
3. The configuration modal displays the current server interval:
   * **Recommended Value:** `60` seconds.
   * **Allowable Range:** `5` to `300` seconds (5 minutes).
4. Enter your desired interval in seconds and click **`Save QR Interval`**.

---

### 2.4 Broadcasting Live Dynamic QR Code Screen
1. In **Event Management**, locate the active event and click **`Actions` -> `Open QR Screen`** (or click the top Live Event banner).
2. The dynamic QR display screen automatically renders the rotating signed QR code with a live countdown timer.
3. Click **`Fullscreen Mode`** (`F11`) to project the dynamic QR code onto a projector or large hall monitor.
4. The system emits pleasant acoustic confirmation chimes when students successfully check in.

---

### 2.5 Finalizing Events & Auto-Processing Absence Fines
1. Once an event concludes, click **`Actions` -> `Complete Event Session`**.
2. To prevent accidental closure, enter your **Administrator Password** to authorize completion.
3. The backend execution engine automatically:
   * Identifies all eligible students who did not scan.
   * Creates `absent` penalty records with total fine calculations.
   * Marks partial attendees with itemized missed slot fines.
   * Logs full details in the System Audit Trail.

---

### 2.6 Fine Tracking, Batch Payments, & Waivers
1. Navigate to **Fines Tracking** (`#fines`) in the sidebar.
2. Use the **Block Filter**, **Event Filter**, or **Search Bar** to locate students.
3. **Recording a Cash Settlement:**
   * Single Student: Click **`Mark Paid`** on the student's fine item.
   * Batch Settlement: Select multiple student checkboxes and click **`Batch Mark Paid`** in the top action bar.
4. **Waiving a Fine (Official Excuse / Medical Certificate):**
   * Click **`Waive Fine`**, enter the formal justification (e.g. *"Approved medical certificate submitted to Dean"*), and confirm. The fine balance is cleared to `₱0.00` and flagged as administrative waiver.

---

### 2.7 Generating Clearance Reports, Printing, & Exporting (Word/CSV)
1. Navigate to **Reports & Exports** (`#reports`) in the sidebar.
2. Filter by specific **Event** or **All Events (Aggregate Summary)**, and choose your target **Block Filter** (e.g. *BSIS 4-A*).
3. The roster automatically groups records into a clean **1-Row Per Student Summary** sorted alphabetically by Last Name (`LAST_NAME, FIRST_NAME M.I.`):
   * Columns: `#`, `Student ID`, `Student Full Name`, `Year / Block`, `Total Fine`, `Paid`, `Balance Due`, `Status`, `Signature`.
4. **Printing Official Roster:**
   * Click **`Print Report`**. The system generates a print layout with institutional header, summary masterlist, clean blank signature boxes, and official signature blocks for **BSIS Department Treasurer** (left) and **BSIS Department Head** (right).
5. **Exporting Digital Files:**
   * Click **`Export Word (.docx)`** to download an editable Microsoft Word document.
   * Click **`Export CSV`** to export raw tabular data for Microsoft Excel.

---

### 2.8 Student Account Provisioning (Single & CSV Batch Import)
1. Navigate to **Student Provisioning** (`#students`).
2. **Single Student Registration:**
   * Fill out Student Number, First Name, Middle Name, Last Name, Institutional Email (`@tpc.edu.ph`), Year Level, and Section Block. Click **`Provision Account`**.
3. **Batch Import from CSV Spreadsheet:**
   * Click **`Import Students CSV`**.
   * Upload a `.csv` file formatted with headers: `student_number,first_name,middle_name,last_name,email,year_level,section_block`.
   * Click **`Upload & Provision Batch`**. The system creates student records and dispatches secure onboarding email links.

---

### 2.9 Reviewing Device Reset Requests
1. When a student replaces a lost phone, their login is held until their previous device binding is unlinked.
2. Navigate to **Device Reset Requests** (`#device-resets`).
3. Inspect the student's submitted reason and previous device information.
4. Click **`Approve Reset`** to unbind the old hardware. The student can now log in on their new mobile phone and bind it automatically.

---

### 2.10 System Audit Logs & Security Accountability
1. Navigate to **System Audit Logs** (`#audit-logs`).
2. The audit trail displays a chronological, tamper-evident record of all system events:
   * Administrator logins and failed password attempts.
   * Fine payments, batch settlements, and waiver reasons.
   * Event creations, modifications, completions, and emergency bypasses.
   * Device reset approvals and unbindings.
3. Filter logs by action type, date range, or student identifier for forensic accountability.

---

# 3. EVENT STAFF & DEPARTMENT TREASURER GUIDE

Event Staff accounts (e.g. BSIS Student Council Officers, Attendance Treasurers) have streamlined access to manage their assigned events without administrative risk.

### 3.1 Assigned Event Operations
1. Log in with your staff credentials. The dashboard displays only events you have been assigned to coordinate.
2. Access the **Live Dynamic QR Display** and project the QR screen during attendance scanning windows.

### 3.2 Emergency Window Bypass Activation
1. If a rainstorm, technical delay, or auditorium power flicker interrupts the scheduled scanning window, click **`Emergency Bypass`**.
2. Select the extension duration (**15, 20, 30, or 60 minutes**) and enter a brief explanation (e.g. *"Power outage delayed morning opening"*).
3. Enter your **Account Password** to authorize.
4. *Note:* Each event permits a maximum quota of **2 Emergency Bypass activations** per staff member to prevent procedural abuse.

### 3.3 Recording Manual Staff Overrides
1. If a student's phone battery is depleted or their camera lens is damaged, staff can validate attendance manually.
2. In the Attendance Module, click **`Manual Override`**.
3. Search for the student by **Student ID Number** or **Email**.
4. Enter the verified reason (e.g. *"Student phone dead; verified physical presence at registration desk"*) and click **`Submit Manual Override`**.
5. The override is stamped with the staff member's name and logged in the audit registry.

### 3.4 Offline Attendance Syncing During Outages
1. If the campus experiences an internet interruption, staff can record scans in offline mode.
2. When the device reconnects to Wi-Fi or cellular data, open **Sync Manager** and click **`Synchronize Offline Records`**.
3. The system uploads the queued records and updates the attendance ledger.

---

# 4. STUDENT USER GUIDE

### 4.1 Account Onboarding & Password Setup
1. Check your institutional email (`@tpc.edu.ph`) for the invitation link sent by the BSIS department.
2. Open the link on your mobile phone browser:
   `https://tpc-bsis.online/onboarding/<token>`
3. Verify your full name and student number.
4. Create a secure password (minimum 8 characters) and click **`Activate Account`**.

---

### 4.2 Single-Device Binding Policy
* **Security Rule:** Your student account is strictly bound to the first mobile device you use to log in.
* **Anti-Buddy Punching:** You cannot log into your account on a friend's phone to scan for them, nor can a classmate log into their account on your phone.
* If you legitimately switch phones or lose your device, submit a **Device Reset Request** through the app.

---

### 4.3 In-App Camera Scanning & Location Verification
1. On the day of the event, log in at `https://tpc-bsis.online/student` on your bound phone.
2. When prompted by your mobile browser, tap **`Allow`** for **Camera Access** and **Location (GPS) Permissions**.
3. Tap **`Scan Dynamic QR Code`**.
4. Aim your camera at the projected screen displaying the dynamic QR code.
5. The system verifies:
   * QR signature and 60-second expiration.
   * GPS location within venue perimeter.
   * Bound device credential.
6. Upon successful verification, your screen displays a green confirmation badge: **`Attendance Recorded: Present`**.

---

### 4.4 Viewing Daily Attendance Progress Timeline
* For **Whole-Day Events**, the student dashboard displays an interactive 4-slot progress tracker:
  * ☀️ **AM Time-In:** Displays scanned check-in timestamp.
  * 🚪 **AM Time-Out:** Displays scanned lunch dismissal timestamp.
  * ⛅ **PM Time-In:** Displays scanned afternoon return timestamp.
  * 🏁 **PM Time-Out:** Displays final afternoon dismissal timestamp.

---

### 4.5 Checking Fine Balances & Clearance Status
1. Tap the **`Fines & Clearance`** tab in the mobile navigation.
2. The dashboard displays:
   * **Total Incurred Fines:** Sum of all unpaid absence or late penalties.
   * **Settled / Paid Fines:** Sum of cleared penalties with treasurer verification receipts.
   * **Clearance Status:** Shows **`CLEARED`** (Green badge) if balance is `₱0.00`, or **`PENDING SETTLEMENT`** (Amber badge) if balances remain.
3. Present your student ID to the **BSIS Department Treasurer** to pay balances or present excused paperwork.

---

### 4.6 Submitting a Device Reset Request
1. If you purchase a new phone or format your browser:
2. Log into the student portal on your new phone.
3. Tap **`Request Device Unbind / Reset`**.
4. Enter a concise explanation (e.g. *"Upgraded to new Android device"*).
5. Tap **`Submit Request`**. Once approved by the administrator, your new phone is automatically bound on next scan.

---

# 5. TROUBLESHOOTING & FREQUENTLY ASKED QUESTIONS (FAQ)

| Issue Encountered | Root Cause | Solution |
| :--- | :--- | :--- |
| **"QR Token Expired"** | The 60-second rotation cycle expired before the scan was completed. | Point your camera at the projected screen and scan the newly generated QR code. |
| **"Geofence Breach / Outside Allowed Radius"** | You are scanning remotely from outside the venue or your phone's GPS is set to battery saver. | Ensure you are inside the event venue. Open Google Maps on your phone to calibrate GPS accuracy, then re-scan. |
| **"Device Credential Mismatch"** | Attempted to log in from an unregistered secondary phone or cleared browser data. | Log in from your registered phone. If you switched phones permanently, submit a **Device Reset Request**. |
| **"Attendance Window Closed"** | Scanning outside the scheduled check-in timeframe. | Check the event schedule. If an official delay occurred, notify the Event Staff to activate Emergency Window Bypass. |
| **"Camera Permission Denied"** | Browser denied camera access. | Open your phone's browser settings (Chrome/Safari), navigate to Site Settings -> Permissions -> Camera, and set to **Allow**. |
| **"Account Blocked Due to Mismatch"** | Multiple unauthorized logins attempted on different phones. | Contact your BSIS Department Administrator to review and unblock your student account. |

---
*End of Institutional User Manual.*
