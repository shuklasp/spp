# SPP Framework: Lifecycle & Security Engine

The SPP Framework provides a robust **Application Lifecycle Management (ALM)** system, enabling seamless development-to-production workflows with industry-standard security.

---

## 1. Lifecycle & Deployment Engine (`SPPSync`)

The synchronization engine is a modularized component located in `spp/modules/spp/sppsync/`. It facilitates bi-directional data and code synchronization between different SPP instances.

### Key Features
- **Incremental Sync**: Only modified files and database records are transferred.
- **State Manifests**: Generates cryptographically hashed state snapshots for environment comparison.
- **Exclusion Rules**: Protects environment-specific files (e.g., `etc/db-config.yml`) from being overwritten.
- **Remote Configuration**: Allows local management of production settings via a secure proxy.

### API Endpoints
- `lifecycle_compare`: Calculates deltas between local and target environments.
- `lifecycle_push`: Transfers specific file/data changes to a remote instance.
- `lifecycle_receive`: Securely accepts incoming synchronization payloads.
- `lifecycle_backup`: Creates full system snapshots in ZIP format.

---

## 2. Environment Management

Environments are configured via the **Deployment Workbench** in the Admin Panel.

### Configuration
1. Click the **gear icon (⚙️)** in the "Environment Target" card.
2. Define the **Remote API URL** and **Deployment Token**.
3. Set **Exclusion Rules** to isolate environment-specific variables.

Settings are persisted in `spp/modules/spp/sppsync/config.yml`.

---

## 3. SPPXDB Encryption Layer

The XML Database (`SPPXDB`) includes a native encryption layer for protecting sensitive data at rest.

### Technical Implementation
- **Cipher**: AES-256-CBC.
- **Key Management**: Dynamic keys loaded from `sys:security.xdb_key` or a default fallback.
- **Field-Level Encryption**: Specific fields can be marked for automatic encryption/decryption.

### Usage Example
```php
$xdb = new \SPPMod\SPPXDB\SPP_XDB('sys', 'security');
$xdb->setEncryptedFields(['value']); // 'value' will be encrypted on disk

// Insert automatically encrypts
$xdb->insert(['key' => 'secret_id', 'value' => 'sensitive_content']);

// Query automatically decrypts
$row = $xdb->queryX("//row[key='secret_id']");
echo $row[0]['value']; // Displays 'sensitive_content'
```

---

## 4. Security & Authorization

### Deployment Tokens
Authorization is handled via unique **Deployment Tokens**.
- **Local Identity**: Found in the "Security & Authorization" card. This token must be provided to remote servers to authorize your requests.
- **Secure Storage**: Tokens are stored in the encrypted `sys.security` XDB collection, ensuring they never appear in plain-text configuration files.
- **Rotation**: Tokens can be rotated instantly to revoke access across all synchronized environments.

---

## 5. Directory Structure
- `spp/modules/spp/sppsync/`: Core synchronization module.
- `spp/modules/spp/sppsync/config.yml`: Environment target definitions (non-sensitive).
- `spp/modules/spp/sppxdb/data/sys/security.xml`: Encrypted storage for sensitive credentials.
- `spp/admin/js/views/lifecycle.js`: Administrative workbench UI.
