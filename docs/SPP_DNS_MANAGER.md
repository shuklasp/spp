# SPP DNS Manager: Comprehensive Administrator & Developer Guide

## 1. Foundational Overview: What is the SPP DNS Manager?

The **SPP DNS Manager** is an enterprise DNS workstation integrated into **SPPAdmin**. It provides a unified, sovereign control plane for managing authoritative Domain Name System (DNS) records, BIND9 daemon configurations, RFC 1035 zone files, and external cloud DNS providers (such as Cloudflare).

Rather than forcing administrators to edit raw zone files via SSH or navigate complex third-party web consoles, the SPP DNS Manager combines:
- A high-performance interactive record grid supporting all major DNS resource record types.
- **Point-in-Time Zone Revision History** tracking every creation, edit, and deletion with microsecond accuracy.
- **1-Click Atomic Rollback** with visual diff preview and automated RFC 1912 SOA serial number increments.
- Bidirectional integration with **Manifest OS (`__spp_file_props.yml`)** and the **SPP File Manager**.

---

## 2. Architecture & Subsystems

```
+-----------------------------------------------------------------------------------+
|                            SPPAdmin Workstation Client                            |
|                (spp/admin/js/views/domains.js - Reactive JavaScript)              |
+-----------------------------------------------------------------------------------+
                                       |
                                       | HTTPS JSON-RPC (api.php)
                                       v
+-----------------------------------------------------------------------------------+
|                             AdminRBAC & CommandBridge                             |
|          Fail-Closed Authorization (admin.domains.* / admin.dns.*)                |
+-----------------------------------------------------------------------------------+
                                       |
                                       | PHP Service Invocations
                                       v
+-----------------------------------------------------------------------------------+
|                   DomainAdminService & BindDaemonService                          |
+-----------------------------------------------------------------------------------+
                                       |
                                       v
+-----------------------------------------------------------------------------------+
|                                 DnsZoneManager                                    |
| - RFC 1035 Parser & Serializer                                                    |
| - Revision History Ledger (var/dns/history/<domain>/rev_*.json)                   |
| - Visual Diff & Rollback Engine                                                   |
| - SOA Serial Calculator (RFC 1912)                                                |
| - Manifest OS Synchronizer (var/dns/__spp_file_props.yml)                         |
+-----------------------------------------------------------------------------------+
         |                                                   |
         v                                                   v
+---------------------------------+         +---------------------------------------+
|  Local BIND9 Zone Files         |         |        Cloudflare DNS Engine          |
|  - var/dns/<domain>.zone        |         |  - Remote zone sync via Cloudflare API|
|  - var/dns/<domain>.json        |         |  - Dual-mode split-horizon DNS        |
+---------------------------------+         +---------------------------------------+
```

---

## 3. Supported Record Types & RFC 1035 Compliance

The DNS workstation provides strict syntax validation for all standard DNS record types:

| Type | Purpose | Syntax Example | RFC Standards |
|---|---|---|---|
| **A** | Maps hostname to IPv4 address | `api.example.com. 3600 IN A 198.51.100.1` | RFC 1035 |
| **AAAA** | Maps hostname to IPv6 address | `api.example.com. 3600 IN AAAA 2001:db8::1` | RFC 3596 |
| **CNAME** | Canonical name alias | `www.example.com. 3600 IN CNAME example.com.` | RFC 1035 |
| **MX** | Mail exchange server with priority | `example.com. 3600 IN MX 10 mail.example.com.` | RFC 1035, RFC 5321 |
| **TXT** | Text annotations (SPF, DKIM, DMARC) | `example.com. 3600 IN TXT "v=spf1 include:_spf.google.com ~all"` | RFC 1464, RFC 7208 |
| **SRV** | Service locator (port, weight, priority) | `_sip._tcp.example.com. 3600 IN SRV 10 60 5060 bigbox.example.com.` | RFC 2782 |
| **CAA** | Certification Authority Authorization | `example.com. 3600 IN CAA 0 issue "letsencrypt.org"` | RFC 8659 |
| **NS** | Authoritative nameservers | `example.com. 86400 IN NS ns1.example.com.` | RFC 1035 |
| **SOA** | Start of Authority (primary NS, hostmaster, serial, timers) | `@ IN SOA ns1.example.com. hostmaster.example.com. ( 2026111401 7200 3600 1209600 3600 )` | RFC 1035, RFC 1912 |

---

## 4. Point-in-Time Zone Revision History

Every administrative action that modifies a zone—whether adding a record, updating an existing IP, deleting an obsolete TXT string, importing a BIND master file, or resetting to factory defaults—automatically writes an **immutable JSON revision snapshot** to disk:

```
var/dns/history/<domain>/
  ├── rev_20261114_124500_102030_a1b2.json
  ├── rev_20261114_125010_405060_c3d4.json
  └── rev_20261114_131522_708090_e5f6.json
```

### Revision Metadata Anatomy:
Each revision document captures:
```json
{
  "id": "rev_20261114_131522_708090_e5f6",
  "domain": "example.com",
  "action": "save_record",
  "summary": "Added A record for portal.example.com -> 198.51.100.99",
  "author": "admin_spk",
  "timestamp": "2026-11-14 13:15:22",
  "timestamp_epoch": 1794729322.70809,
  "records_before_count": 8,
  "records_after_count": 9,
  "records": [ ... full snapshot of all active records ... ]
}
```

Revisions are chronologically ordered down to the microsecond, ensuring an unbroken audit lineage. The workstation stores up to 50 historical revisions per domain.

---

## 5. Visual Rollback Diff Preview

Selecting the **🕒 Zone History & Rollback** button in the zone header opens the historical revision ledger. For any past revision, clicking **Preview Diff** compares the active zone against that historical snapshot:

```
+-----------------------------------------------------------------------------------+
|  Rollback Preview: example.com -> rev_20261114_124500                            |
+-----------------------------------------------------------------------------------+
|  Revision Author: admin_spk               Created: 2026-11-14 12:45:00            |
|  Summary: Baseline production configuration                                       |
|                                                                                   |
|  Changes that will be applied on rollback:                                        |
|  [-] REMOVED:  bad-gateway.example.com.  (A  10.10.10.99)                        |
|  [+] RESTORED: api.example.com.          (A  198.51.100.42)                       |
|  [*] MODIFIED: mail.example.com.         (MX 10 -> MX 20)                         |
|                                                                                   |
|  SOA Serial Transition: 2026111403  ==>  2026111404 (Auto-incremented)           |
|                                                                                   |
|  [  Confirm 1-Click Rollback  ]                           [  Cancel  ]            |
+-----------------------------------------------------------------------------------+
```

---

## 6. 1-Click Atomic Zone Rollback

When an administrator confirms a rollback:
1. **Prior Record State Loaded**: The exact record set from the target revision is retrieved.
2. **RFC 1912 Serial Increment**: The SOA serial number is dynamically calculated and strictly incremented (`YYYYMMDDnn` format) to guarantee that secondary nameservers and DNS resolvers acknowledge the zone update.
3. **Rollback Audit Logging**: A new revision of type `rollback` is created in `var/dns/history/<domain>/`, recording who triggered the rollback, which revision was restored, and when it occurred.
4. **BIND Zone Compilation**: `var/dns/<domain>.zone` is atomically rewritten with the restored records and fresh serial.
5. **Manifest OS Synchronization**: `var/dns/__spp_file_props.yml` is updated immediately with the new zone file SHA-256 digest.

---

## 7. Deep Manifest OS Integration

All DNS zone files live under `var/dns/`. The DNS Manager automatically manages `var/dns/__spp_file_props.yml`:

```yaml
version: '1.0'
directory:
  description: 'SPP Authoritative DNS zone repository and BIND configurations'
  usage: 'Stores RFC 1035 zone files, JSON metadata, and revision history'
  stability: 'stable'
  author: 'SPP DNS Manager'
  review_date: '2027-11-14'
  security:
    roles: ['admin']
    allow_network: ['1.1.1.1', '8.8.8.8', '9.9.9.9', 'api.cloudflare.com']
    allow_filesystem: ['var/dns', '/etc/bind', '/etc/named.conf', '/var/named']
    allow_shell: false
  attributes:
    layer: 'infrastructure'
    tags: ['dns', 'bind', 'zones', 'authoritative']
files:
  example.com.zone:
    description: 'BIND RFC 1035 authoritative zone file for example.com'
    usage: 'Loaded by BIND9 daemon and verified by DnsZoneManager'
    stability: 'stable'
    author: 'DNS Administrator'
    review_date: '2027-11-14'
    checksum: 'a9f24d9c72e259e869c9b1391512db44d5d65427da97cfbcff96384a45012345'
    security:
      roles: ['admin']
      allow_network: true
      allow_filesystem: true
      allow_shell: false
    attributes:
      domain: 'example.com'
      provider: 'bind9'
      layer: 'infrastructure'
      tags: ['dns', 'zone', 'bind', 'example.com']
```

Clicking **🏷️ Sync Manifest** in the DNS workstation header instantly regenerates this metadata and recomputes cryptographic checksums.

---

## 8. Cross-Workstation Integration: Navigating Between DNS & Files

The File Manager and DNS Manager are linked bidirectionally:
- In the **DNS Manager**, the zone toolbar includes a **📁 File Manager** button that navigates directly to `var/dns/` in the File Manager workstation.
- In the **File Manager**, any file ending with `.zone` displays a **🌐 DNS Zone** badge and an inline **🌐 DNS** button, jumping straight to that domain's interactive DNS editor.
