<?php

namespace App\Repositories\Contracts;

use App\Models\Account;
use Illuminate\Support\Collection;

interface AccountRepositoryInterface
{
    public function getAllOrderByCreatedAtDesc(): Collection;

    public function create(array $attributes): Account;

    public function findById(int $id): ?Account;

    public function findByIdOrFail(int $id): Account;

    public function update(Account $account, array $attributes): bool;

    public function delete(Account $account): bool;
}
