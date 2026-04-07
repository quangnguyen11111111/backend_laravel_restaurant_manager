<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefreshToken extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = 'token';
    protected $keyType = 'string';

    protected $fillable = [
        'token',
        'account_id',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Get the account that owns the refresh token
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Check if the token is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
