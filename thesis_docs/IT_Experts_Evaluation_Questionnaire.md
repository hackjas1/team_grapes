# SYSTEM EVALUATION QUESTIONNAIRE (FOR IT EXPERTS / EVALUATORS)
### Evaluation of Secure Dynamic QR Code-Based Attendance Monitoring System
**Framework:** ISO/IEC 25010 Software Quality Standards

---

### PART I. EXPERT EVALUATOR PROFILE

* **Name (Optional):** ____________________________________________
* **Current Role / Position:**  
  [ ] Software Engineer / Developer  
  [ ] Systems Analyst / Architect  
  [ ] IT Instructor / Professor  
  [ ] Database / Network Administrator  
  [ ] Other: ____________________________________
* **Years of IT Experience:** [ ] 1–3 years &nbsp;&nbsp;&nbsp;&nbsp; [ ] 4–6 years &nbsp;&nbsp;&nbsp;&nbsp; [ ] 7–10 years &nbsp;&nbsp;&nbsp;&nbsp; [ ] 10+ years
* **Primary Specialization:** ____________________________________________

---

### RATING SCALE & INSTRUCTIONS

Please evaluate the technical architecture, security implementation, and code quality of the system by placing a checkmark in the appropriate column corresponding to your rating:

| Scale | Rating | Verbal Interpretation |
|:---:|:---:|:---|
| **5** | Strongly Agree (SA) | The system exceptionally satisfies the technical standard. |
| **4** | Agree (A) | The system adequately satisfies the technical standard. |
| **3** | Neutral (N) | The system moderately satisfies the standard with minor reservations. |
| **2** | Disagree (D) | The system fails to meet the technical standard adequately. |
| **1** | Strongly Disagree (SD) | The system does not meet the technical standard at all. |

---

### PART II. TECHNICAL QUALITY EVALUATION (ISO/IEC 25010)

#### A. SECURITY
*(Confidentiality, Integrity, Authenticity, Non-Repudiation, Accountability)*

| # | Technical Quality Indicator | 5 | 4 | 3 | 2 | 1 |
|---|---|:---:|:---:|:---:|:---:|:---:|
| **1** | The dynamic QR token mechanism uses secure hashing/encryption and rolling time-windows to effectively prevent token replay and code sharing. | | | | | |
| **2** | The single-device hardware binding algorithm strictly prevents unauthorized student credential sharing across multiple devices. | | | | | |
| **3** | Role-Based Access Control (RBAC) and API authentication (Sanctum) correctly protect restricted endpoints from unauthorized access. | | | | | |
| **4** | The system enforces strict GPS geofencing validation server-side to prevent spoofed or out-of-bounds attendance logging. | | | | | |
| **5** | The centralized audit trail comprehensively logs administrative overrides, device reset requests, and validation failures for non-repudiation. | | | | | |

---

#### B. MAINTAINABILITY
*(Modularity, Reusability, Analysability, Modifiability, Testability)*

| # | Technical Quality Indicator | 5 | 4 | 3 | 2 | 1 |
|---|---|:---:|:---:|:---:|:---:|:---:|
| **1** | The backend architecture follows the MVC pattern cleanly, properly separating business logic, controllers, models, and API routing. | | | | | |
| **2** | The codebase adheres to structured naming conventions, modular component design, and readable code documentation. | | | | | |
| **3** | The database schema uses normalized relational tables, proper foreign keys, constraints, and migrations for ease of maintenance. | | | | | |
| **4** | System configurations, environment variables (`.env`), and API base URLs are properly externalized to facilitate easy system updates. | | | | | |
| **5** | Modular API endpoints and structured JSON response schemas allow new features or client interfaces to be integrated without altering core logic. | | | | | |

---

#### C. RELIABILITY
*(Availability, Fault Tolerance, Recoverability, Maturity)*

| # | Technical Quality Indicator | 5 | 4 | 3 | 2 | 1 |
|---|---|:---:|:---:|:---:|:---:|:---:|
| **1** | The application handles network timeouts, offline states, and server disconnects gracefully with appropriate user feedback. | | | | | |
| **2** | The offline attendance storage (SQLite queue) reliably stores scans locally and prevents data loss during event connectivity drops. | | | | | |
| **3** | The offline-to-online sync mechanism accurately reconciles batch attendance data and handles duplicate conflicts without corrupting database records. | | | | | |
| **4** | Database transactions (ACID compliance) ensure data integrity during simultaneous attendance submissions and fine payments. | | | | | |
| **5** | The dynamic QR generator server process runs continuously and recovers reliably without crashing during prolonged event sessions. | | | | | |

---

#### D. COMPATIBILITY
*(Interoperability, Co-existence)*

| # | Technical Quality Indicator | 5 | 4 | 3 | 2 | 1 |
|---|---|:---:|:---:|:---:|:---:|:---:|
| **1** | The RESTful API adheres to standard HTTP methods and standard JSON data structures for seamless client-server communication. | | | | | |
| **2** | The web dashboard functions consistently across major modern web browsers (Chrome, Edge, Firefox, Safari) without rendering issues. | | | | | |
| **3** | The mobile client co-exists properly with other device applications without causing memory leaks or resource contention. | | | | | |
| **4** | The system integrates smoothly with external tunneling/hosting services (Cloudflare Tunnel, HTTPS) for secure external connectivity. | | | | | |
| **5** | Exported attendance reports (PDF/Excel/CSV) are structured and compatible with standard office productivity software. | | | | | |

---

#### E. PORTABILITY
*(Adaptability, Installability, Replaceability)*

| # | Technical Quality Indicator | 5 | 4 | 3 | 2 | 1 |
|---|---|:---:|:---:|:---:|:---:|:---:|
| **1** | The mobile client (built with React Native / Expo) adapts cleanly to different screen sizes, aspect ratios, and mobile OS versions. | | | | | |
| **2** | The backend system dependencies (PHP, Composer, MySQL, Node.js) can be deployed across different server environments (Linux/Windows/Cloud). | | | | | |
| **3** | The mobile application package installs and initializes smoothly without complex manual user configuration. | | | | | |
| **4** | The administrative web dashboard is responsive and accessible across both desktop monitors and tablet devices. | | | | | |
| **5** | The backend database and asset storage can be migrated or backed up to alternative hosting providers with minimal reconfiguration. | | | | | |

---

### PART III. TECHNICAL COMMENTS & RECOMMENDATIONS

1. **Architectural Strengths Observed:**  
   ____________________________________________________________________________________________________  
   ____________________________________________________________________________________________________  

2. **Technical Limitations or Vulnerabilities Identified:**  
   ____________________________________________________________________________________________________  
   ____________________________________________________________________________________________________  

3. **Recommended Technical Improvements (Scalability, Security, Architecture):**  
   ____________________________________________________________________________________________________  
   ____________________________________________________________________________________________________  

<br/>

**Evaluator Signature:** ____________________________ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; **Date:** ____________________________
