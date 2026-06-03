# Feature Breakdown (Current Project Inventory)

> Dokumen ini memetakan fitur yang **sudah ada** di project saat ini (berdasarkan route/controller/menu aktif).

## 1. Authentication & Profile

- **Authentication**
  - Login / logout / register / forgot password
  - Session-based auth + middleware `auth, verified`
- **Profile**
  - Update profile
  - Change password
  - Switch branch aktif user

## 2. Dashboard

- **Main Dashboard**
  - KPI cards dan ringkasan data
  - Data endpoints untuk task/client/subscription list
- **Dashboard Configuration**
  - Konfigurasi dashboard per role

## 3. Human Resources

- **Employee (User Management)**
  - CRUD user/employee
  - Import data + download template
  - Detail user
  - Impersonation (`login as`, stop impersonation)
- **Division**
  - CRUD division
- **Position**
  - CRUD position

## 4. Business Structure

- **Holding**
  - CRUD holding business unit
- **Company**
  - CRUD company
- **Branch**
  - CRUD branch

## 5. Customer

- **Customer Group**
  - CRUD customer group
- **Customer List**
  - CRUD customer
  - Remove attachment customer

### 5.1 Planned Extension: CRM Membership (Schema `crm`)

- **Membership Configuration (Planned)**
  - Konfigurasi poin membership berbasis nominal transaksi (contoh: **1 poin = 100** rupiah, berlaku kelipatan).
  - Penyimpanan konfigurasi di schema `crm` agar terpisah dari master customer.
- **Customer Membership Link (Planned)**
  - Relasi akun membership ke customer (setiap customer dapat memiliki akun membership dengan saldo poin).
  - Integrasi informasi poin & membership pada halaman detail customer.

## 6. Access Management & Settings

- **Role Management**
  - CRUD role
- **Menu Management**
  - CRUD menu
- **Notification Configuration**
  - Config notification
  - API list, unread count, mark read, mark all read
- **Parameter Management**
  - CRUD parameter
  - CRUD parameter detail
- **Import Data Settings**
  - Halaman import data utility

## 7. Product Master

- **Product Nature**
  - CRUD nature
- **Product Unit**
  - CRUD unit
- **Product Category**
  - CRUD category
- **Attribute Definition**
  - CRUD attribute
  - Add/edit/delete attribute value
- **Product Tag**
  - CRUD tag
- **Product Collection**
  - CRUD collection
- **Product Price List**
  - CRUD price list
  - Endpoint active price lists

## 8. Product Items (Unified Product Master)

- **Product Item CRUD**
  - List/detail/edit/delete/restore
- **3-step Product Insert**
  - Step 1, Step 2, Step 3 flow
  - Generate code endpoint
- **Variant Management**
  - Create/edit/delete variant
  - Variant data endpoint
- **Unit Conversion Management**
  - Add/edit/delete conversion
  - Temp conversion update/remove
- **Import/Export**
  - Import product
  - Download template
  - Export data

## 9. Inventory

- **Stock View**
  - Stock listing
- **Stock Opname**
  - Save opname adjustment
- **Stock Adjustment**
  - Save stock adjustment

## 10. Purchasing

- **Supplier**
  - CRUD supplier
- **Purchase Order**
  - CRUD purchase order
  - Receive PO
  - Receive detail
  - Supplier by type endpoint

## 11. POS & Transaction

- **POS**
  - POS page
  - Load variants by selected price list
  - Process payment transaction
- **Transaction**
  - Transaction list
  - Transaction detail
- **Method Payment**
  - CRUD method payment

### 11.1 Planned Extension: POS ↔ CRM Membership

- **Earning Poin Saat Transaksi (Planned)**
  - Hitung poin membership otomatis dari nominal transaksi berdasarkan konfigurasi poin pada schema `crm`.
  - Simpan riwayat earning poin per transaksi dan hubungkan ke akun membership customer (jika transaksi terkait customer).

## 12. Reporting

- **Summary Sales Report**
  - KPI + trend + payment summary
- **Purchase Order Report**
  - Purchase order reporting page
- **Transaction Report**
  - Reporting transaksi (list + filter + KPI)
- **Stock Reports**
  - Stock movement / stock card
  - Stock history

## 13. Shared / Utility Features

- **Helper API**
  - Provinces, cities, business units
- **File Upload**
  - Upload endpoint
- **Component Showcase**
  - Internal page for UI components
