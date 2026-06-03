<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Employees;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class NotificationController extends Controller
{
    /**
     * Show notification list page for current user
     */
    public function listView(Request $request)
    {
        $user = auth()->user();
        
        $query = \App\Models\Notification::where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('filter_read')) {
            if ($request->filter_read === 'read') {
                $query->where('is_read', true);
            } elseif ($request->filter_read === 'unread') {
                $query->where('is_read', false);
            }
        }

        if ($request->filled('filter_type')) {
            $query->where('type', $request->filter_type);
        }

        $notifications = $query->paginate(20);
        
        // Get filter values for view
        $filterRead = $request->get('filter_read', '');
        $filterType = $request->get('filter_type', '');

        return view('admin.access-management.notification.list', compact('notifications', 'filterRead', 'filterType'));
    }

    /**
     * Show notification configuration page
     */
    public function indexView(Request $request)
    {
        $config = config('notifications.recipients', []);
        
        // Get all roles for dropdown
        $roles = Role::whereNull('deleted_at')
            ->orderBy('name', 'ASC')
            ->get();
        
        // Get all employees/developers for dropdown
        $employees = Employees::whereNull('deleted_at')
            ->orderBy('fullname', 'ASC')
            ->get();
        
        return view('admin.access-management.notification.index', compact('config', 'roles', 'employees'));
    }

    /**
     * Update notification configuration
     */
    public function updateConfig(Request $request)
    {
        $request->validate([
            'recipients' => 'required|array',
        ], [
            'recipients.required' => 'Recipients configuration is required.',
            'recipients.array' => 'Recipients must be an array.',
        ]);

        // Process recipients data
        $recipients = [];
        foreach ($request->recipients as $module => $actions) {
            foreach ($actions as $action => $config) {
                // Handle developers - now all modules use array directly
                $developers = $config['developers'] ?? [];
                if (!is_array($developers)) {
                    $developers = [];
                }
                
                $recipients[$module][$action] = [
                    'roles' => $config['roles'] ?? [],
                    'developers' => $developers,
                    'all' => isset($config['all']) && $config['all'] == '1',
                ];
            }
        }

        // Get current config structure
        $configContent = $this->generateConfigFile($recipients);
        
        // Write to config file
        $configPath = config_path('notifications.php');
        
        try {
            File::put($configPath, $configContent);
            
            // Clear config cache
            Artisan::call('config:clear');
            
            return redirect()->route('notifications.index.view')->with('success', 'Notification configuration updated successfully');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update configuration: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Generate config file content with proper formatting
     */
    private function generateConfigFile($recipients)
    {
        $content = "<?php\n\n";
        $content .= "return [\n";
        $content .= "    /*\n";
        $content .= "    |--------------------------------------------------------------------------\n";
        $content .= "    | Notification Recipients Configuration\n";
        $content .= "    |--------------------------------------------------------------------------\n";
        $content .= "    |\n";
        $content .= "    | Configure notification recipients for each module/action.\n";
        $content .= "    | You can specify recipients by role_id, developer_id, or 'all' for all users.\n";
        $content .= "    |\n";
        $content .= "    */\n\n";
        $content .= "    'recipients' => [\n";
        
        foreach ($recipients as $module => $actions) {
            $content .= "        '{$module}' => [\n";
            foreach ($actions as $action => $config) {
                $content .= "            '{$action}' => [\n";
                
                // Roles
                $roles = $config['roles'] ?? [];
                if (empty($roles)) {
                    $content .= "                'roles' => [],\n";
                } else {
                    $content .= "                'roles' => [\n";
                    foreach ($roles as $role) {
                        $roleValue = is_string($role) ? "'{$role}'" : $role;
                        $content .= "                    {$roleValue},\n";
                    }
                    $content .= "                ],\n";
                }
                
                // Developers
                $developers = $config['developers'] ?? [];
                if ($developers === 'assigned') {
                    $content .= "                'developers' => 'assigned',\n";
                } elseif (empty($developers)) {
                    $content .= "                'developers' => [],\n";
                } else {
                    $content .= "                'developers' => [\n";
                    foreach ($developers as $developer) {
                        $developerValue = is_string($developer) ? "'{$developer}'" : $developer;
                        $content .= "                    {$developerValue},\n";
                    }
                    $content .= "                ],\n";
                }
                
                // All
                $all = $config['all'] ?? false;
                $content .= "                'all' => " . ($all ? 'true' : 'false') . ",\n";
                
                $content .= "            ],\n";
            }
            $content .= "        ],\n\n";
        }
        
        $content .= "    ],\n";
        $content .= "];\n";
        
        return $content;
    }

    /**
     * Get notifications for current user (API)
     */
    public function getNotifications(Request $request)
    {
        $user = auth()->user();
        $limit = $request->get('limit', 10);
        
        // Get all notifications (not just unread) for dropdown display
        $notifications = \App\Services\NotificationService::getForUser($user, [], $limit);
        
        return response()->json([
            'success' => true,
            'notifications' => $notifications->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'type' => $notification->type,
                    'module' => $notification->module,
                    'url' => $notification->url,
                    'is_read' => $notification->is_read,
                    'created_at' => $notification->created_at->diffForHumans(),
                ];
            }),
        ]);
    }

    /**
     * Get notifications data for DataTable (API)
     */
    public function listData(Request $request)
    {
        $user = auth()->user();
        
        $query = \App\Models\Notification::where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('filter_read')) {
            if ($request->filter_read === 'read') {
                $query->where('is_read', true);
            } elseif ($request->filter_read === 'unread') {
                $query->where('is_read', false);
            }
        }

        if ($request->filled('filter_module')) {
            $query->where('module', $request->filter_module);
        }

        if ($request->filled('filter_type')) {
            $query->where('type', $request->filter_type);
        }

        $notifications = $query->get();

        $data = [];
        foreach ($notifications as $index => $notification) {
            $typeClass = $notification->type === 'error' ? 'danger' : 
                         ($notification->type === 'warning' ? 'warning' : 
                         ($notification->type === 'success' ? 'success' : 'info'));
            $typeIcon = $notification->type === 'error' ? 'alert-circle' : 
                       ($notification->type === 'warning' ? 'alert-triangle' : 
                       ($notification->type === 'success' ? 'check' : 'info-circle'));
            
            $data[] = [
                'DT_RowIndex' => $index + 1,
                'id' => $notification->id,
                'type' => $notification->type ?? 'info',
                'type_class' => $typeClass,
                'type_icon' => $typeIcon,
                'title' => $notification->title ?? '',
                'message' => $notification->message ?? '',
                'module' => $notification->module ?? '-',
                'url' => $notification->url,
                'is_read' => $notification->is_read,
                'read_badge' => $notification->is_read 
                    ? '<span class="badge bg-label-success">Read</span>' 
                    : '<span class="badge bg-label-warning">Unread</span>',
                'created_at' => $notification->created_at ? $notification->created_at->format('Y-m-d H:i:s') : '',
                'created_at_human' => $notification->created_at ? $notification->created_at->diffForHumans() : '',
            ];
        }

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Get unread count (API)
     */
    public function getUnreadCount()
    {
        $user = auth()->user();
        $count = \App\Services\NotificationService::getUnreadCount($user);
        
        return response()->json([
            'success' => true,
            'count' => $count,
        ]);
    }

    /**
     * Mark notification as read (API)
     */
    public function markAsRead(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:notifications,id',
        ]);

        $user = auth()->user();
        $notification = \App\Models\Notification::find($request->id);
        
        // Ensure the notification belongs to the current user
        if (!$notification || $notification->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $success = \App\Services\NotificationService::markAsRead($request->id);
        
        return response()->json([
            'success' => $success,
        ]);
    }

    /**
     * Mark all notifications as read (API)
     */
    public function markAllAsRead()
    {
        $user = auth()->user();
        $count = \App\Services\NotificationService::markAllAsRead($user);
        
        return response()->json([
            'success' => true,
            'count' => $count,
        ]);
    }
}
