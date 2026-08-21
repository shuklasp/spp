# SPPReport Platinum Features Guide (Novice-First)

Welcome to the SPPReport Platinum Features tutorial! If you're new to the SPP Framework, `SPPReport` is the built-in engine for querying databases and generating beautiful reports. 

Recently, the engine was upgraded with "Platinum Tier" capabilities to handle massive enterprise scale, data privacy, and AI-driven automation. This guide will walk you through what these features are and exactly how to use them.

## 1. Dynamic Data Masking (PII Redaction)

### What is it?
When dealing with sensitive user data (like emails, Social Security Numbers, or revenue), you don't want every employee to see the raw values. Dynamic Data Masking automatically redacts (hides) parts of the data on-the-fly when the report is viewed, unless the user has specific permission.

### How it works
The `DataMasker` service intercepts the data row by row. If the user doesn't have the required role (e.g., `admin` or `pii_viewer`), it replaces the data with asterisks (e.g., `j***@example.com` or `***-**-1234`).

### Step-by-Step Example
In your report's YAML configuration file (e.g., `etc/sppreports/my_report.yml`), add the following rules:

```yaml
unmask_roles: "admin, hr_manager"
masking_rules:
  user_email: "EMAIL"
  ssn: "SSN"
  salary: "NUMBER"
```
Now, if a normal user views the report, they will see `s***@gmail.com`, but an `hr_manager` will see `satya@gmail.com`.

---

## 2. Zero-Latency Materialized Snapshots

### What is it?
Imagine a heavy report that calculates total sales across 50 million rows. Running this every time a user clicks "View" would crash your database. Materialized Snapshots solve this by running the query *once* (usually overnight) and saving the results into a high-speed binary file (`XdbBinaryIndexer`).

### How it works
When the SPP cron engine runs, it detects the `materialized: true` flag. It executes the massive query and writes the result to `var/snapshots/my_report.xdb`. When users load the report during the day, `api.php` bypasses the SQL database entirely and streams data instantly from the binary file!

### Step-by-Step Example
Add the materialized flag to your YAML config:

```yaml
materialized: true
cron_schedule: "0 2 * * *" # Runs every day at 2:00 AM
```

---

## 3. Pre-Execution Query Cost Estimation

### What is it?
A safety net for your database. If a user tries to run a poorly constructed report (e.g., joining two massive tables without an index), the database could freeze. The Query Cost Estimator prevents this.

### How it works
Before running the actual SQL query, `class.sppreport.php` runs an `EXPLAIN` statement against MySQL or PostgreSQL. It estimates how many rows will be scanned. If it exceeds 1,000,000 rows without an index, it rejects the synchronous request and asks the user to schedule it as a background task via `DagJobOrchestrator`.

### How to use it
This feature is completely automatic! You don't need to configure anything. If a query is too heavy, the user will see a yellow warning banner prompting them to "Schedule Background Export".

---

## 4. AI-Driven Anomaly Detection Alerts

### What is it?
Instead of receiving a boring scheduled email every day with an attached PDF, the SPP AI can read the report *before* it sends the email. It will only email you if a specific, natural-language condition is met (an anomaly).

### How it works
The cron daemon `sppreport_cron.php` uses `AiReportService` to evaluate your dataset against the condition. If the AI determines the condition is `true`, it fires the alert. Otherwise, it stays silent.

### Step-by-Step Example
In your YAML configuration, add an `alert_condition`:

```yaml
cron_schedule: "0 8 * * 1" # Every Monday at 8 AM
cron_email: "ceo@sppsystem.local"
alert_condition: "Notify if total revenue dropped by more than 15% compared to the previous week, or if there is a sudden spike in failed transactions."
```
Now, the CEO only gets an email when something requires their immediate attention!
