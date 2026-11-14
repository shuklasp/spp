# SPP File Manager: Comprehensive Administrator & Developer Guide

## 1. Foundational Overview: What is the SPP File Manager?

The **SPP File Manager** is a sovereign, enterprise-grade filesystem, storage, and hosting management workstation built directly into **SPPAdmin**. Unlike conventional web file managers that merely present a shallow list of filenames, sizes, and timestamps, the SPP File Manager is deeply integrated with **SPP's native Manifest OS (`__spp_file_props.yml`)**.

Every directory in an SPP project carries rich, declarative metadata describing:
- The architectural purpose, usage patterns, and stability of every file.
- Strict security capabilities (`allow_network`, `allow_filesystem`, `allow_env`, `allow_shell`).
- Cryptographic SHA-256 integrity digests and HMAC digital signatures for tamper detection.
- Application discovery routes, view contracts, and telemetry parameters.

The SPP File Manager surfaces this metadata as a first-class citizen at every stage of filesystem exploration, editing, and deployment.

---

## 2. Architectural Architecture & Invariants

```
+-----------------------------------------------------------------------------------+
|                            SPPAdmin Workstation Client                            |
|                 (spp/admin/js/views/files.js - Reactive JavaScript)               |
+-----------------------------------------------------------------------------------+
                                       |
                                       | HTTPS JSON-RPC (api.php)
                                       v
+-----------------------------------------------------------------------------------+
|                             AdminRBAC & CommandBridge                             |
|           Fail-Closed Authorization & Hierarchical Scope Checking                 |
|   (admin.files.read, admin.files.write, admin.files.delete, admin.files.props)     |
+-----------------------------------------------------------------------------------+
                                       |
                                       | PHP Service Invocations
                                       v
+-----------------------------------------------------------------------------------+
|                     StorageAdminService & ArchiveAdminService                     |
|           Atomic Writes (.bak Rollback), Zip-Slip & Bomb Containment             |
+-----------------------------------------------------------------------------------+
         |                                                   |
         v                                                   v
+---------------------------------+         +---------------------------------------+
|  Manifest OS (FilePropsManager)  |         |   DNS Zone Bridge (DnsZoneManager)    |
| - __spp_file_props.yml Engine   |         | - Auto-detects RFC 1035 .zone files   |
| - Capability Security Guards    |         | - Cross-navigates to DNS Workstation  |
| - Cryptographic Signer & Healer |         | - Real-time zone manifest sync        |
+---------------------------------+         +---------------------------------------+
```

### Core Invariants:
1. **Zero Configuration Drift**: Every create, edit, copy, move, or delete operation automatically keeps the associated `__spp_file_props.yml` synchronized.
2. **Preservation of Unknown & Custom Attributes**: When manifests are updated or healed, custom attributes, annotations, and extension keys are strictly preserved.
3. **Atomic Writes with Automatic Rollback**: All file and manifest modifications use staged temporary writes (`.__tmp_*`) and create stable `.bak` snapshots. If an I/O error or syntax failure occurs, the prior state is instantly restored.
4. **Fail-Closed Security**: Decompression bomb caps (10,000 files, 500 MB max), Zip-Slip canonical path containment, and expiring HMAC signed URLs prevent malicious filesystem exploitation.

---

## 3. Directory Exploration & The Manifest Intelligence Banner

When an administrator navigates into any directory within the workspace, the File Manager loads both the physical directory contents and the local `__spp_file_props.yml` manifest.

### The Manifest Intelligence Banner
At the top of the file listing, a live banner displays the health and contract metadata of the current directory:
- **Status Pills**:
  - 🟢 **Manifest Active**: The directory has a valid, up-to-date `__spp_file_props.yml`.
  - 🟡 **Manifest Missing**: No manifest was found on disk; 1-click self-healing is offered.
  - 🔴 **Manifest Corrupted**: YAML syntax error or invalid structure detected.
- **Directory Description & Purpose**: Human-readable architectural documentation explaining why this folder exists and how its contents are invoked.
- **Architectural Layer & Stability**: Shows whether the code is `core`, `domain`, `presentation`, `infrastructure`, or `operational`, alongside stability lifecycles (`stable`, `beta`, `experimental`, `deprecated`).
- **Maintainer & SLA Review Date**: Displays the author/team responsible and highlights overdue review dates.
- **Security Capabilities Summary**: High-level badge showing whether network or filesystem access is constrained.
- **Quick Action Buttons**:
  - **✏️ Edit Directory Props**: Opens the structured metadata editor for this folder.
  - **🩺 Manifest Health**: Runs the diagnostic health scanner on this directory tree.
  - **📝 Raw YAML**: Opens the atomic raw manifest editor with live syntax validation and diff preview.

---

## 4. The 6-Tab Properties Inspector

Selecting the **🏷️ Props** button on any file or folder opens the dedicated Properties Inspector modal:

```
+---------------------------------------------------------------------------------------+
|  File Properties: src/School/Controllers/StudentController.php                        |
|  [Overview] [Docs & Tags] [Application] [Security & Capabilities] [Integrity] [Raw]   |
+---------------------------------------------------------------------------------------+
|  Name: StudentController.php                     Type: PHP Source File                |
|  Canonical Path: C:/projects/apache/school1/src/School/Controllers/StudentController.php |
|  Size: 14.2 KB                                   MIME: text/x-php                     |
|  Parent Manifest: src/School/Controllers/__spp_file_props.yml (🟢 Active)             |
|                                                                                       |
|  Description:                                                                         |
|  [Student enrollment, profile management, and academic grading HTTP controller     ]  |
|                                                                                       |
|  Usage Pattern:                                                                       |
|  [Dispatched via application router to handle /students and /students/:id          ]  |
|                                                                                       |
|  Architectural Layer: [presentation v]          Stability: [stable v]                 |
|  Author / Maintainer: [Academics Team     ]     Review Date: [2027-01-01]            |
|  Tags (comma separated): [students, controller, academics, grading                 ]  |
|                                                                                       |
|  Security Policy & Capabilities:                                                      |
|  [x] Allow Network Access                        [x] Allow Local Filesystem           |
|  [ ] Allow Environment Variable Read             [ ] Allow Shell Command Execution    |
|                                                                                       |
|  Cryptographic Integrity (SHA-256):                                                   |
|  e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855 (Verified 🟢)     |
|                                                                                       |
|  [  Save Properties  ]   [  Verify Integrity  ]   [  Sign File  ]   [  Cancel  ]      |
+---------------------------------------------------------------------------------------+
```

### Tab Breakdown:
1. **Overview Tab**: Displays the canonical absolute path, file size, MIME type, disk timestamps, parent folder, and manifest synchronization status.
2. **Docs & Tags Tab**: Allows editing the file description, copy-pasteable usage examples, architectural layer, author, review date, and semantic tags.
3. **Application & Framework Tab**: Exposes application binding properties (`is_app`, `app_name`, `entry_point`, `routes`, and `feature_flag` toggles).
4. **Security & Capabilities Tab**: Configures runtime capabilities enforced by `ManifestSecurityGuard` (`allow_network`, `allow_filesystem`, `allow_env`, `allow_shell`, `roles`, and `ip_allowlist`).
5. **Integrity Tab**: Shows the recorded SHA-256 digest, compares it live against the file on disk to detect tampering, and provides 1-click cryptographic HMAC signing.
6. **Contracts & Telemetry Tab**: Declares interface implementations, SLO execution budgets (`max_duration_ms`, `max_memory_mb`), and OpenTelemetry tracing rates.

---

## 5. Dual-Mode Manifest Editor

The File Manager provides two distinct editing experiences depending on administrator preference:

### Mode 1: The Structured Property Editor (Default)
A clean, form-based interface that organizes properties into intuitive sections. Form fields validate input types (e.g., ensuring numeric values for SLO budgets, valid dates for review periods, and approved data classifications). Unknown or custom properties are preserved automatically in the background.

### Mode 2: The Raw YAML Editor (Advanced)
For power users and DevOps engineers who want direct access to YAML:
- **Live Syntax Validation**: Validates indentation, keys, and syntax in real-time before saving. If invalid YAML is entered, the exact line and error message are presented.
- **Diff Preview**: Clicking **Show Diff** computes a colored side-by-side or line-by-line comparison between the active manifest on disk and the proposed changes.
- **Atomic Persistence**: Validated YAML is written through staged temporary files and backed up to `.bak` files, ensuring no corrupt YAML ever reaches production.

---

## 6. Manifest Health Dashboard

Clicking the **🩺 Manifest Health** button on the workstation toolbar launches the interactive health analysis engine:

- **Workspace & Directory Scoping**: Administrators can scan either the current directory subtree or trigger a full workspace audit.
- **Health Score (0–100%)**: An automated composite score reflecting:
  - 100%: All files tracked, valid YAML, no tampering, up-to-date review dates.
  - Penalties: Deductions for missing manifests (-15 pts), untracked files (-2 pts), invalid YAML (-20 pts), and tampered SHA-256 digests (-30 pts).
- **Issue Breakdown**: Categorizes issues into:
  - 🟡 Missing manifests.
  - ⚪ Untracked files on disk.
  - 🟠 Orphaned manifest entries (files deleted from disk but remaining in metadata).
  - 🔵 Stale review dates requiring developer audit.
  - 🔴 Cryptographic integrity violations.
- **Automated Self-Healing**: Clicking **✨ Heal Manifests** automatically scans disk files, computes fresh SHA-256 checksums, infers missing descriptions from code docblocks, prunes orphaned entries, and restores the health score to 100%.

---

## 7. Storage Safety & Defensive Invariants

The File Manager includes enterprise-grade defenses against malicious storage vectors:

### Zip-Slip Containment
When extracting compressed zip or tar archives, `ArchiveAdminService` inspects every entry's path. Any entry containing path traversal sequences (`../`, `..\`) or absolute roots attempting to escape the extraction target is strictly blocked, logged in the audit trail, and reported to the administrator.

### Decompression Bomb Defense
To prevent denial-of-service via zip bombs:
- Extraction enforces a hard ceiling of **10,000 total files**.
- Total decompressed output is throttled to a maximum of **500 MB**.
If either ceiling is breached during extraction, the stream terminates immediately.

### Constant-Time Signed URLs
Temporary download URLs (`LocalDisk::temporaryUrl()`) use HMAC-SHA256 signatures with mandatory expiration timestamps. The system strictly fails closed (`RuntimeException`) if encryption keys (`SPP_APP_KEY`) are missing, preventing insecure fallback secrets.

---

## 8. DNS Zone Navigation Bridges

The File Manager seamlessly connects filesystem storage to DNS management:
- Files with the `.zone` extension or files residing within `var/dns/` are badged with a distinct **🌐 DNS Zone** pill.
- Clicking the **🌐 DNS** button or choosing **Open in DNS Manager** from the row context menu immediately navigates the administrator to `#domains?zone=<domain>`, opening that zone directly inside the RFC 1035 DNS Zone Editor.
