<?php

namespace App\Repositories;

use App\Models\Account;
use App\Repositories\Contracts\AccountRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class AccountRepository implements AccountRepositoryInterface
{
    public function getAllOrderByCreatedAtDesc(): Collection
    {
        return Account::query()->orderBy('created_at', 'desc')->get();
    }

    public function create(array $attributes): Account
    {
        return Account::query()->create($attributes);
    }

    public function findById(int $id): ?Account
    {
        return Account::query()->find($id);
    }

    public function findByIdOrFail(int $id): Account
    {
        return Account::query()->findOrFail($id);
    }

    public function update(Account $account, array $attributes): bool
    {
        return $account->update($attributes);
    }

    public function delete(Account $account): bool
    {
        return (bool) $account->delete();
    }
}
