<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\Employees;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class NotificationService
{
    /**
     * Create a new notification.
     *
     * @param string|User $user User ID or User instance
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $type Notification type (info, success, warning, error)
     * @param array $options Additional options (module, related, url, created_by)
     * @return Notification
     */
    public static function create($user, string $title, string $message, string $type = 'info', array $options = []): Notification
    {
        $userId = $user instanceof User ? $user->id : $user;

        $notificationData = [
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'module' => $options['module'] ?? null,
            'url' => $options['url'] ?? null,
            'created_by' => $options['created_by'] ?? auth()->id(),
        ];

        // Handle related model (polymorphic)
        if (isset($options['related']) && $options['related'] instanceof Model) {
            $notificationData['related_id'] = $options['related']->id;
            $notificationData['related_type'] = get_class($options['related']);
        }

        return Notification::create($notificationData);
    }

    /**
     * Create notification for multiple users.
     *
     * @param array|Collection $users Array of user IDs or User instances
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $type Notification type
     * @param array $options Additional options
     * @return Collection
     */
    public static function createForUsers($users, string $title, string $message, string $type = 'info', array $options = []): Collection
    {
        $notifications = collect();

        foreach ($users as $user) {
            $notifications->push(self::create($user, $title, $message, $type, $options));
        }

        return $notifications;
    }

    /**
     * Create notification for all users (broadcast).
     *
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $type Notification type
     * @param array $options Additional options
     * @return Collection
     */
    public static function broadcast(string $title, string $message, string $type = 'info', array $options = []): Collection
    {
        $users = User::all();
        return self::createForUsers($users, $title, $message, $type, $options);
    }

    /**
     * Mark notification as read.
     *
     * @param string $notificationId Notification ID
     * @return bool
     */
    public static function markAsRead(string $notificationId): bool
    {
        $notification = Notification::find($notificationId);

        if ($notification) {
            $notification->markAsRead();
            return true;
        }

        return false;
    }

    /**
     * Mark all notifications as read for a user.
     *
     * @param string|User $user User ID or User instance
     * @return int Number of notifications marked as read
     */
    public static function markAllAsRead($user): int
    {
        $userId = $user instanceof User ? $user->id : $user;

        return Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
                'updated_by' => auth()->id(),
            ]);
    }

    /**
     * Delete notification.
     *
     * @param string $notificationId Notification ID
     * @return bool
     */
    public static function delete(string $notificationId): bool
    {
        $notification = Notification::find($notificationId);

        if ($notification) {
            $notification->delete();
            return true;
        }

        return false;
    }

    /**
     * Get notifications for a user.
     *
     * @param string|User $user User ID or User instance
     * @param array $filters Filters (read, unread, module, type)
     * @param int $limit Limit number of results
     * @return Collection
     */
    public static function getForUser($user, array $filters = [], int $limit = null): Collection
    {
        $userId = $user instanceof User ? $user->id : $user;

        // Ensure userId is valid
        if (empty($userId)) {
            return collect();
        }

        $query = Notification::where('user_id', $userId)
            ->whereNull('deleted_at') // Explicitly exclude soft deleted notifications
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (isset($filters['read'])) {
            $query->where('is_read', (bool) $filters['read']);
        }

        if (isset($filters['unread'])) {
            $query->where('is_read', false);
        }

        if (isset($filters['module'])) {
            $query->where('module', $filters['module']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Get unread count for a user.
     *
     * @param string|User $user User ID or User instance
     * @return int
     */
    public static function getUnreadCount($user): int
    {
        $userId = $user instanceof User ? $user->id : $user;

        // Ensure userId is valid
        if (empty($userId)) {
            return 0;
        }

        return Notification::where('user_id', $userId)
            ->whereNull('deleted_at') // Explicitly exclude soft deleted notifications
            ->where('is_read', false)
            ->count();
    }

    /**
     * Create notification based on module configuration.
     * Supports role-based, developer-based, or 'all' notifications.
     *
     * @param string $module Module name (e.g., 'client', 'subscription', 'task')
     * @param string $action Action name (e.g., 'created')
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $type Notification type
     * @param array $options Additional options (related, url, developer_id for task assignment)
     * @return Collection
     */
    public static function notifyByModule(string $module, string $action, string $title, string $message, string $type = 'info', array $options = []): Collection
    {
        $config = config("notifications.recipients.{$module}.{$action}", []);

        $recipients = collect();

        // Handle 'all' notification
        if (isset($config['all']) && $config['all'] === true) {
            $users = User::all();
            return self::createForUsers($users, $title, $message, $type, array_merge($options, ['module' => $module]));
        }

        // Handle role-based notifications
        if (isset($config['roles']) && is_array($config['roles']) && count($config['roles']) > 0) {
            $roleUsers = User::whereIn('role_id', $config['roles'])->get();
            $recipients = $recipients->merge($roleUsers);
        }

        // Handle developer-based notifications
        if (isset($config['developers'])) {
            if ($config['developers'] === 'assigned' && isset($options['developer_id'])) {
                // For task assignment, notify the assigned developer (in addition to roles)
                $developer = Employees::find($options['developer_id']);
                if ($developer) {
                    $user = User::where('employee_id', $developer->id)->first();
                    if ($user) {
                        $recipients->push($user);
                    }
                }
            } elseif (is_array($config['developers']) && count($config['developers']) > 0) {
                // Notify specific developers
                $developerEmployees = Employees::whereIn('id', $config['developers'])->get();
                foreach ($developerEmployees as $employee) {
                    $user = User::where('employee_id', $employee->id)->first();
                    if ($user) {
                        $recipients->push($user);
                    }
                }
            }
        }

        // Remove duplicates
        $recipients = $recipients->unique('id');

        if ($recipients->isEmpty()) {
            return collect();
        }

        return self::createForUsers($recipients, $title, $message, $type, array_merge($options, ['module' => $module]));
    }
}

