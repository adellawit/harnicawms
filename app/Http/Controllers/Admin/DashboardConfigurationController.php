<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\DashboardConfiguration;
use App\Models\DashboardConfigurationTeamMember;
use App\Models\Employees;
use App\Models\Menu;
use App\Services\SidebarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardConfigurationController extends Controller
{
    /**
     * Show list of roles for dashboard configuration
     */
    public function indexView(Request $request)
    {
        // Load sidebar menus and permissions
        SidebarService::loadSidebarsAndPermissions();

        return view('admin.access-management.dashboard-configuration.index');
    }

    /**
     * Show dashboard configuration form for a role
     */
    public function editView($roleId)
    {
        // Load sidebar menus and permissions
        SidebarService::loadSidebarsAndPermissions();

        $role = Role::findOrFail($roleId);

        // Get existing dashboard configurations
        $configurations = DashboardConfiguration::where('role_id', $roleId)
            ->whereNull('deleted_at')
            ->get()
            ->keyBy(function ($item) {
                return $item->widget ? $item->section . '.' . $item->widget : $item->section;
            });

        // Define dashboard sections and widgets (POS & WMS)
        $dashboardSections = [
            'executive_overview' => [
                'name' => 'Executive Overview',
                'icon' => 'ti ti-dashboard',
                'widgets' => [
                    'kpi_cards' => 'KPI Cards (Revenue, Transactions, Profit)',
                    'sales_trend_chart' => 'Sales Trend Chart (Monthly)',
                    'outlet_performance_chart' => 'Outlet Performance Chart',
                ]
            ],
            'sales' => [
                'name' => 'Sales Dashboard',
                'icon' => 'ti ti-chart-bar',
                'widgets' => [
                    'kpi_cards' => 'KPI Cards (Revenue, Transactions, AOV)',
                    'sales_trend_chart' => 'Sales Trend Chart (Daily)',
                    'payment_methods_chart' => 'Payment Methods Chart',
                    'top_products' => 'Top Selling Products',
                    'top_categories' => 'Top Categories',
                    'sales_per_outlet' => 'Sales per Outlet Chart',
                ]
            ],
            'inventory' => [
                'name' => 'Inventory Dashboard',
                'icon' => 'ti ti-packages',
                'widgets' => [
                    'kpi_cards' => 'KPI Cards (SKU, Stock, Value, Low Stock)',
                    'stock_by_category_chart' => 'Stock by Category Chart',
                    'low_stock_table' => 'Low Stock Products Table',
                    'dead_stock_table' => 'Dead Stock Table',
                ]
            ],
            'procurement' => [
                'name' => 'Procurement Dashboard',
                'icon' => 'ti ti-truck-delivery',
                'widgets' => [
                    'kpi_cards' => 'KPI Cards (PO, Pending, Received)',
                    'po_status_chart' => 'PO by Status Chart',
                    'top_suppliers' => 'Top Suppliers Table',
                ]
            ],
            'warehouse' => [
                'name' => 'Warehouse / WMS Dashboard',
                'icon' => 'ti ti-building-warehouse',
                'widgets' => [
                    'kpi_cards' => 'KPI Cards (Inbound, Outbound, Alerts)',
                    'inbound_outbound_chart' => 'Inbound vs Outbound Chart',
                    'recent_activity' => 'Recent Warehouse Activity',
                ]
            ],
            'outlet_operations' => [
                'name' => 'Outlet Operations Dashboard',
                'icon' => 'ti ti-building-store',
                'widgets' => [
                    'kpi_cards' => 'KPI Cards (Outlets, Avg Revenue, Refund, Void)',
                    'hourly_chart' => 'Sales per Hour Chart',
                    'outlet_table' => 'Sales per Outlet Table',
                ]
            ],
            'finance' => [
                'name' => 'Finance Dashboard',
                'icon' => 'ti ti-report-money',
                'widgets' => [
                    'kpi_cards' => 'KPI Cards (Revenue, COGS, Profit, Margin)',
                    'revenue_cogs_chart' => 'Revenue vs COGS Chart',
                    'top_profit_products' => 'Top Profit Products Table',
                ]
            ],
            'customer' => [
                'name' => 'Customer Dashboard',
                'icon' => 'ti ti-users',
                'widgets' => [
                    'kpi_cards' => 'KPI Cards (Total, New, Returning, Avg)',
                    'top_customers' => 'Top Customers Table',
                ]
            ],
            'alerts' => [
                'name' => 'Alert & Monitoring',
                'icon' => 'ti ti-alert-triangle',
                'widgets' => [
                    'kpi_cards' => 'Alert KPI Cards',
                ]
            ],
        ];

        // Get all employees/developers for team workload configuration
        $employees = Employees::whereNull('deleted_at')
            ->orderBy('fullname', 'ASC')
            ->get();

        // Get existing team member configurations for this role
        $teamMemberConfigs = DashboardConfigurationTeamMember::where('role_id', $roleId)
            ->whereNull('deleted_at')
            ->pluck('employee_id')
            ->toArray();

        return view('admin.access-management.dashboard-configuration.edit', compact('role', 'configurations', 'dashboardSections', 'employees', 'teamMemberConfigs'));
    }

    /**
     * Update dashboard configuration for a role
     */
    public function update(Request $request)
    {
        $request->validate([
            'role_id' => 'required|string|exists:roles,id',
            'configurations' => 'nullable|array',
            'team_members' => 'nullable|array',
            'team_members.*' => 'nullable|string|exists:employees,id',
        ], [
            'role_id.required' => 'Role ID is required.',
            'role_id.exists' => 'Role not found.',
            'configurations.array' => 'Configurations must be an array.',
            'team_members.array' => 'Team members must be an array.',
        ]);

        $roleId = $request->role_id;

        // Soft delete existing configurations for this role
        DashboardConfiguration::where('role_id', $roleId)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => now(),
                'deleted_by' => auth('web')->id(),
            ]);

        // Create new configurations using updateOrCreate to handle duplicates and restore soft deleted records
        if ($request->has('configurations') && is_array($request->configurations)) {
            foreach ($request->configurations as $key => $isVisible) {
                $isVisible = filter_var($isVisible, FILTER_VALIDATE_BOOLEAN);
                
                // Parse section and widget from key (format: "section" or "section.widget")
                $parts = explode('.', $key);
                $section = $parts[0];
                $widget = isset($parts[1]) ? $parts[1] : null;

                // Only create if is_visible is true (to reduce database clutter)
                if ($isVisible) {
                    // Check if record exists (including soft deleted)
                    $config = DashboardConfiguration::withTrashed()
                        ->where('role_id', $roleId)
                        ->where('section', $section)
                        ->where('widget', $widget)
                        ->first();

                    if ($config) {
                        // Restore if soft deleted
                        if ($config->trashed()) {
                            $config->restore();
                        }
                        // Update the record
                        $config->update([
                            'is_visible' => true,
                            'updated_by' => auth('web')->id(),
                            'deleted_at' => null,
                            'deleted_by' => null,
                        ]);
                    } else {
                        // Create new if doesn't exist
                        DashboardConfiguration::create([
                            'role_id' => $roleId,
                            'section' => $section,
                            'widget' => $widget,
                            'is_visible' => true,
                            'created_by' => auth('web')->id(),
                        ]);
                    }
                }
            }
        }

        // Handle team member configurations for team_workload section
        
        // Soft delete existing team member configurations for this role
        DashboardConfigurationTeamMember::where('role_id', $roleId)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => now(),
                'deleted_by' => auth('web')->id(),
            ]);

        // Create/restore team member configurations
        if ($request->has('team_members') && is_array($request->team_members)) {
            foreach ($request->team_members as $employeeId) {
                if ($employeeId) {
                    // Check if record exists (including soft deleted)
                    $teamMember = DashboardConfigurationTeamMember::withTrashed()
                        ->where('role_id', $roleId)
                        ->where('employee_id', $employeeId)
                        ->first();

                    if ($teamMember) {
                        // Restore if soft deleted
                        if ($teamMember->trashed()) {
                            $teamMember->restore();
                        }
                        // Update the record
                        $teamMember->update([
                            'updated_by' => auth('web')->id(),
                            'deleted_at' => null,
                            'deleted_by' => null,
                        ]);
                    } else {
                        // Create new if doesn't exist
                        DashboardConfigurationTeamMember::create([
                            'role_id' => $roleId,
                            'employee_id' => $employeeId,
                            'created_by' => auth('web')->id(),
                        ]);
                    }
                }
            }
        }

        return redirect()->back()->with('success', 'Dashboard configuration updated successfully.');
    }
}
