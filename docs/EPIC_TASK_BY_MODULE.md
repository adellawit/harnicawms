# Epic & Task by Module (Based on Existing Features)

> Mapping epic di bawah ini merepresentasikan fitur yang sudah tersedia di project saat ini.
> Kolom status bisa diisi tim sesuai kondisi delivery.

## 1. Authentication & Profile

### Epic AUTH-01: Account Session & Profile

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `AUTH-01-T01` | Login/logout/register/forgot-password flow | Backend+Frontend | P0 | Existing |
| `AUTH-01-T02` | Profile update & change password | Backend+Frontend | P0 | Existing |
| `AUTH-01-T03` | Branch switching per user context | Backend+Frontend | P1 | Existing |

## 2. Human Resources

### Epic HR-01: Employee Management

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `HR-01-T01` | Employee list, create, edit, delete, restore | Backend+Frontend | P0 | Existing |
| `HR-01-T02` | Employee detail & import/template | Backend+Frontend | P1 | Existing |
| `HR-01-T03` | Impersonation (`login as`) | Backend+Frontend | P1 | Existing |

### Epic HR-02: Division & Position

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `HR-02-T01` | Division CRUD | Backend+Frontend | P1 | Existing |
| `HR-02-T02` | Position CRUD | Backend+Frontend | P1 | Existing |

## 3. Business & Master Data

### Epic BIZ-01: Business Unit Structure

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `BIZ-01-T01` | Holding CRUD | Backend+Frontend | P1 | Existing |
| `BIZ-01-T02` | Company CRUD | Backend+Frontend | P1 | Existing |
| `BIZ-01-T03` | Branch CRUD | Backend+Frontend | P1 | Existing |

### Epic MD-01: Parameters & Settings

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `MD-01-T01` | Parameter CRUD | Backend+Frontend | P1 | Existing |
| `MD-01-T02` | Parameter detail CRUD | Backend+Frontend | P1 | Existing |
| `MD-01-T03` | Dashboard configuration per role | Backend+Frontend | P2 | Existing |

## 4. Access Management & Notification

### Epic IAM-01: Access Control

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `IAM-01-T01` | Role CRUD | Backend+Frontend | P0 | Existing |
| `IAM-01-T02` | Menu CRUD | Backend+Frontend | P1 | Existing |

### Epic NOTIF-01: Notification Center

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `NOTIF-01-T01` | Notification config page | Backend+Frontend | P2 | Existing |
| `NOTIF-01-T02` | Notification APIs (list/count/mark read) | Backend+Frontend | P1 | Existing |

## 5. Customer

### Epic CUST-01: Customer Domain

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `CUST-01-T01` | Customer group CRUD | Backend+Frontend | P1 | Existing |
| `CUST-01-T02` | Customer CRUD | Backend+Frontend | P1 | Existing |
| `CUST-01-T03` | Remove customer attachment | Backend+Frontend | P2 | Existing |

## 6. Product Master

### Epic PROD-01: Product Reference Master

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `PROD-01-T01` | Nature/unit/category CRUD | Backend+Frontend | P0 | Existing |
| `PROD-01-T02` | Attribute + value CRUD | Backend+Frontend | P1 | Existing |
| `PROD-01-T03` | Tag & collection CRUD | Backend+Frontend | P1 | Existing |
| `PROD-01-T04` | Price list CRUD + active endpoint | Backend+Frontend | P1 | Existing |

### Epic PROD-02: Product Item Lifecycle

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `PROD-02-T01` | Product item CRUD | Backend+Frontend | P0 | Existing |
| `PROD-02-T02` | 3-step insert flow | Backend+Frontend | P0 | Existing |
| `PROD-02-T03` | Variant CRUD & variant data API | Backend+Frontend | P0 | Existing |
| `PROD-02-T04` | Unit conversion add/edit/delete | Backend+Frontend | P1 | Existing |
| `PROD-02-T05` | Import/export/template | Backend+Frontend | P1 | Existing |

## 7. Inventory & Purchasing

### Epic INV-01: Inventory Operations

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `INV-01-T01` | Stock view | Backend+Frontend | P1 | Existing |
| `INV-01-T02` | Stock opname save | Backend+Frontend | P1 | Existing |
| `INV-01-T03` | Stock adjustment save | Backend+Frontend | P1 | Existing |
| `INV-01-T04` | Product price save | Backend+Frontend | P1 | Existing |

### Epic PUR-01: Purchasing

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `PUR-01-T01` | Supplier CRUD | Backend+Frontend | P1 | Existing |
| `PUR-01-T02` | Purchase order CRUD | Backend+Frontend | P0 | Existing |
| `PUR-01-T03` | Purchase order receiving flow | Backend+Frontend | P0 | Existing |

## 8. POS & Transaction

### Epic POS-01: Point of Sales

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `POS-01-T01` | POS page and cart flow | Backend+Frontend | P0 | Existing |
| `POS-01-T02` | Variant pricing API by price list | Backend+Frontend | P0 | Existing |
| `POS-01-T03` | Payment processing + stock deduction | Backend+Frontend | P0 | Existing |

### Epic TRX-01: Transaction Admin

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `TRX-01-T01` | Transaction list page | Backend+Frontend | P1 | Existing |
| `TRX-01-T02` | Transaction detail page | Backend+Frontend | P1 | Existing |
| `TRX-01-T03` | Method payment CRUD | Backend+Frontend | P1 | Existing |

## 9. Reporting

### Epic RPT-01: Sales & Transaction Reporting

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `RPT-01-T01` | Summary sales report | Backend+Frontend | P1 | Existing |
| `RPT-01-T02` | Transaction report | Backend+Frontend | P1 | Existing |

### Epic RPT-02: Purchasing & Inventory Reporting

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `RPT-02-T01` | Purchase order report | Backend+Frontend | P1 | Existing |
| `RPT-02-T02` | Stock movement / stock card report | Backend+Frontend | P1 | Existing |
| `RPT-02-T03` | Stock history report | Backend+Frontend | P1 | Existing |

## 11. CRM & Membership (Planned)

### Epic CRM-01: Membership Configuration & Points

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `CRM-01-T01` | Migration schema `crm` + tabel membership point configuration | Backend | P1 | Planned |
| `CRM-01-T02` | Seeder menu **Configuration** untuk CRM Membership | Backend | P1 | Planned |
| `CRM-01-T03` | CRUD backend membership point configuration (validation, business rule 1 poin = 100 dan kelipatan nominal transaksi) | Backend | P1 | Planned |
| `CRM-01-T04` | CRUD frontend membership point configuration (list/create/edit/delete) | Frontend | P1 | Planned |

### Epic CRM-02: Customer Membership Integration

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `CRM-02-T01` | Link akun membership ke customer (relasi `customer` ↔ `crm.membership_accounts`) | Backend+Frontend | P1 | Planned |
| `CRM-02-T02` | Penambahan informasi membership & poin pada detail customer | Backend+Frontend | P2 | Planned |

### Epic CRM-03: POS Points Accrual

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `CRM-03-T01` | Hitung poin membership otomatis saat transaksi POS (berdasarkan konfigurasi poin) | Backend+Frontend | P1 | Planned |
| `CRM-03-T02` | Simpan riwayat poin per transaksi pada schema `crm` | Backend | P1 | Planned |
| `CRM-03-T03` | Tampilan riwayat poin pada halaman customer / membership | Frontend | P2 | Planned |

## 10. Tracking Summary (Current Inventory)

| Module | Epic Count | Task Count | Status |
|---|---:|---:|---|
| Authentication & Profile | 1 | 3 | Existing |
| Human Resources | 2 | 5 | Existing |
| Business & Master Data | 2 | 6 | Existing |
| Access Mgmt & Notification | 2 | 4 | Existing |
| Customer | 1 | 3 | Existing |
| Product Master | 2 | 9 | Existing |
| Inventory & Purchasing | 2 | 7 | Existing |
| POS & Transaction | 2 | 6 | Existing |
| Reporting | 2 | 5 | Existing |
| CRM & Membership | 3 | 9 | Planned |
