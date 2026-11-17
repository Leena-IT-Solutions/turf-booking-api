<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDelivery extends Model
{
    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_NO_DEVICE = 'no_device';

    public const STATUS_NOT_CONFIGURED = 'not_configured';

    public const STATUS_SKIPPED_DISABLED = 'skipped_disabled';

    public const STATUS_SKIPPED_OPT_OUT = 'skipped_user_opt_out';

    protected $fillable = [
        'user_id',
        'event',
        'channel',
        'title',
        'body',
        'data',
        'status',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'data' => 'array',
        'sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
