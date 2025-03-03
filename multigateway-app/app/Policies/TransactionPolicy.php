<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TransactionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any transactions.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the transaction.
     */
    public function view(User $user, Transaction $transaction): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create transactions.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can process refunds.
     */
    public function refund(User $user, Transaction $transaction): bool
    {
        return $user->hasAnyRole(['ADMIN', 'FINANCE']) && $transaction->status !== 'REFUNDED';
    }

    /**
     * Determine whether the user can view sensitive details of the transaction.
     */
    public function viewSensitiveDetails(User $user, Transaction $transaction): bool
    {
        return $user->hasAnyRole(['ADMIN', 'FINANCE']);
    }
}
