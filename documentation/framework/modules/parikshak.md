# Core Module: Parikshak (Evolutionary Testing)

Parikshak is SPP's state-of-the-art Automated Evolutionary Testing engine. It is designed to proactively discover bugs, security vulnerabilities, and data integrity issues by "evolving" test scenarios over time.

---

## 1. Basic Philosophy
Parikshak follows the **"Chaos Engineering"** philosophy. Instead of just running static unit tests, it performs dynamic system scanning and "fuzzing" to identify edge cases that a human developer might miss.

---

## 2. Architecture
The module functions as an autonomous service that interacts with the framework's Entity and Database layers.

### Key Components:
*   **System Scanner**: Analyzes all registered entities and their metadata to understand the system structure.
*   **Mutation Engine**: Generates semi-random but valid data to test CRUD operations.
*   **Integrity Validator**: Checks database state after mutations to ensure no data corruption occurred.
*   **Evolutionary Loop**: Learns from previous failures to generate more complex and difficult test cases in subsequent runs.

---

## 3. Usage & CLI

### Running a System Scan
```powershell
php spp.php parikshak:scan
```

### Running Evolutionary Fuzzing
```powershell
php spp.php parikshak:fuzz --limit=100
```

---

## 4. Diagnostic Reporting
Parikshak generates detailed reports in the `var/parikshak/` directory, highlighting:
*   **Primary Key Conflicts**: Instances where the system attempted to overwrite existing data.
*   **Validation Failures**: Cases where the framework allowed invalid data to persist.
*   **Performance Bottlenecks**: Identifies entities that take significantly longer to process during mutations.

---
[Back to Modules Index](index.md)
