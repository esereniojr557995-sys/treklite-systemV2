<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

/** One line in the Owner's activity trail ("who did what"). */
class ActivityLog extends Model
{
    protected $fillable = ['user_id', 'action_type', 'action', 'description'];

    public const ACTION_LABELS = [
        'sale_void'        => 'Sale voided',
        'product_add'      => 'Product added',
        'product_edit'     => 'Product edited',
        'product_archive'  => 'Product archived',
        'product_restore'  => 'Product restored',
        'product_delete'   => 'Product deleted',
        'option_add'       => 'Option added',
        'option_edit'      => 'Option edited',
        'option_toggle'    => 'Option enabled/disabled',
        'option_delete'    => 'Option deleted',
        'staff_add'        => 'Staff added',
        'staff_edit'       => 'Staff edited',
        'staff_delete'     => 'Staff deleted',
        'backup_create'    => 'Backup created',
        'backup_restore'   => 'Backup restored',
    ];

    /** Label color of each action [background, text], so different actions are never all the same color. */
    public const ACTION_COLORS = [
        'sale_void'       => ['#dc3545', '#ffffff'],   // red
        'product_add'     => ['#198754', '#ffffff'],   // green
        'product_edit'    => ['#0d6efd', '#ffffff'],   // blue
        'product_archive' => ['#fd7e14', '#ffffff'],   // orange
        'product_restore' => ['#20c997', '#000000'],   // teal
        'product_delete'  => ['#842029', '#ffffff'],   // dark red
        'option_add'      => ['#6f42c1', '#ffffff'],   // purple
        'option_edit'     => ['#0dcaf0', '#000000'],   // cyan
        'option_toggle'   => ['#ffc107', '#000000'],   // yellow
        'option_delete'   => ['#d63384', '#ffffff'],   // pink
        'staff_add'       => ['#6610f2', '#ffffff'],   // indigo
        'staff_edit'      => ['#7d5a3c', '#ffffff'],   // brown
        'staff_delete'    => ['#343a40', '#ffffff'],   // charcoal
        'backup_create'   => ['#6c757d', '#ffffff'],   // gray
        'backup_restore'  => ['#a61e4d', '#ffffff'],   // plum
    ];

    /** Group of each action (saved in activity_logs.action_type); the Activity Log can color the entries by it. */
    public const ACTION_TYPES = [
        'sale'    => 'Sales',
        'product' => 'Products',
        'user'    => 'Accounts',
        'backup'  => 'Backups',
        'other'   => 'Other',
    ];

    /** Which group an action belongs to, from its code (sale_void -> sale, staff_add -> user, ...). */
    public static function typeOf(string $action): string
    {
        return match (true) {
            str_starts_with($action, 'sale_')    => 'sale',
            str_starts_with($action, 'product_'),
            str_starts_with($action, 'option_')  => 'product',
            str_starts_with($action, 'staff_')   => 'user',
            str_starts_with($action, 'backup_')  => 'backup',
            default                              => 'other',
        };
    }

    /** Writes one line. It must never break the action being logged, so any error is ignored. */
    public static function record(string $action, string $description): void
    {
        try {
            static::create([
                'user_id'     => auth()->id(),
                'action_type' => self::typeOf($action),
                'action'      => $action,
                'description' => mb_substr($description, 0, 500),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function getActionLabelAttribute(): string
    {
        return self::ACTION_LABELS[$this->action] ?? ucfirst(str_replace('_', ' ', $this->action));
    }

    /** [background, text] color of this action's label. */
    public function getActionColorAttribute(): array
    {
        return self::ACTION_COLORS[$this->action] ?? ['#6c757d', '#ffffff'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
