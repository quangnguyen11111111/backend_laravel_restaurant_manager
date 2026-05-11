<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Socket extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'socket_id',
        'account_id',
        'guest_id',
    ];

    /**
     * Get the account that owns the socket connection.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the guest that owns the socket connection.
     */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    /**
     * Scope to find socket by account ID.
     */
    public function scopeByAccount($query, $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Scope to find socket by guest ID.
     */
    public function scopeByGuest($query, $guestId)
    {
        return $query->where('guest_id', $guestId);
    }
}
