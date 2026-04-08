<?php

namespace App\Repositories;

use App\Models\Account;
use App\Models\RefreshToken;
use App\Repositories\Contracts\AuthRepositoryInterface;

class AuthRepository implements AuthRepositoryInterface
{
    public function findAccountByEmail(string $email): ?Account
    {
        return Account::query()->where('email', $email)->first();
    }

    public function findRefreshTokenWithAccount(string $token): ?RefreshToken
    {
        return RefreshToken::query()
            ->where('token', $token)
            ->with('account')
            ->first();
    }

    public function createRefreshToken(string $token, int $accountId, string $expiresAt): RefreshToken
    {
        return RefreshToken::query()->create([
            'token' => $token,
            'account_id' => $accountId,
            'expires_at' => $expiresAt,
        ]);
    }

    public function deleteRefreshToken(string $token): void
    {
        RefreshToken::query()->where('token', $token)->delete();
    }
}
