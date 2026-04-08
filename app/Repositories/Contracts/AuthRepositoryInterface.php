<?php

namespace App\Repositories\Contracts;

use App\Models\Account;
use App\Models\RefreshToken;

interface AuthRepositoryInterface
{
    public function findAccountByEmail(string $email): ?Account;

    public function findRefreshTokenWithAccount(string $token): ?RefreshToken;

    public function createRefreshToken(string $token, int $accountId, string $expiresAt): RefreshToken;

    public function deleteRefreshToken(string $token): void;
}
