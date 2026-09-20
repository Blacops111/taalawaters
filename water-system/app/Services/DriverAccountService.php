<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DriverAccountService
{
    public function link(Driver $driver, string $email): Driver
    {
        return DB::transaction(function () use ($driver, $email) {
            $lockedDriver = Driver::query()
                ->lockForUpdate()
                ->findOrFail($driver->id);

            if ($lockedDriver->user_id !== null) {
                throw ValidationException::withMessages([
                    'login_email' => 'This driver already has a linked login account.',
                ]);
            }

            $user = User::query()
                ->where('email', strtolower(trim($email)))
                ->lockForUpdate()
                ->first();

            if (! $user) {
                throw ValidationException::withMessages([
                    'login_email' => 'No existing user account was found with that email address.',
                ]);
            }

            if ($user->role === 'admin') {
                throw ValidationException::withMessages([
                    'login_email' => 'An administrator account cannot be converted into a driver account.',
                ]);
            }

            $alreadyLinked = Driver::query()
                ->where('user_id', $user->id)
                ->whereKeyNot($lockedDriver->id)
                ->lockForUpdate()
                ->exists();

            if ($alreadyLinked) {
                throw ValidationException::withMessages([
                    'login_email' => 'That user account is already linked to another driver.',
                ]);
            }

            $user->update([
                'role' => 'driver',
            ]);

            $lockedDriver->update([
                'user_id' => $user->id,
            ]);

            return $lockedDriver->fresh('user');
        }, 3);
    }

    public function unlink(Driver $driver): Driver
    {
        return DB::transaction(function () use ($driver) {
            $lockedDriver = Driver::query()
                ->lockForUpdate()
                ->findOrFail($driver->id);

            if ($lockedDriver->user_id === null) {
                throw ValidationException::withMessages([
                    'login_account' => 'This driver does not have a linked login account.',
                ]);
            }

            $user = User::query()
                ->lockForUpdate()
                ->find($lockedDriver->user_id);

            $lockedDriver->update([
                'user_id' => null,
            ]);

            if ($user && $user->role === 'driver') {
                $user->update([
                    'role' => 'staff',
                ]);
            }

            return $lockedDriver->fresh('user');
        }, 3);
    }
}
