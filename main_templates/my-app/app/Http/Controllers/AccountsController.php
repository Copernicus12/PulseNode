<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Support\NotificationCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountsController extends Controller
{
    public function index(): View
    {
        $authModel = $this->authModelClass();
        $users = $authModel::query()
            ->get()
            ->sortBy(fn ($user) => strtolower((string) $user->name))
            ->sortBy(fn ($user) => $user->account_status === $authModel::ACCOUNT_STATUS_PENDING ? 0 : 1)
            ->sortBy(fn ($user) => $user->is_blocked ? 0 : 1)
            ->sortBy(fn ($user) => match ($user->role) {
                $authModel::ROLE_ADMIN => 0,
                $authModel::ROLE_MODERATOR => 1,
                default => 2,
            })
            ->values();

        $summary = [
            'total' => $authModel::query()->count(),
            'admins' => $authModel::query()->where('role', $authModel::ROLE_ADMIN)->count(),
            'moderators' => $authModel::query()->where('role', $authModel::ROLE_MODERATOR)->count(),
            'active_guests' => $authModel::query()
                ->where('role', $authModel::ROLE_GUEST)
                ->where('is_blocked', false)
                ->whereNotNull('guest_expires_at')
                ->where('guest_expires_at', '>', now())
                ->count(),
            'blocked' => $authModel::query()->where('is_blocked', true)->count(),
            'pending_requests' => $authModel::query()
                ->where('account_status', $authModel::ACCOUNT_STATUS_PENDING)
                ->count(),
        ];

        $roles = $authModel::roles();

        return view('accounts.index', compact('users', 'summary', 'roles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $authModel = $this->authModelClass();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique($authModel)],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::in($authModel::roles())],
            'guest_duration_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
        ]);

        $guestExpiry = $this->guestExpiryFromInput($data['role'], $data['guest_duration_hours'] ?? null);
        if ($guestExpiry instanceof RedirectResponse) {
            return $guestExpiry;
        }

        $authModel::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'guest_expires_at' => $guestExpiry,
            'is_blocked' => false,
            'blocked_at' => null,
            'account_status' => $authModel::ACCOUNT_STATUS_ACTIVE,
            'requested_at' => null,
            'approved_at' => now(),
            'rejected_at' => null,
        ]);

        return redirect()
            ->route('accounts.index')
            ->with('accounts_success', 'Account created successfully.');
    }

    public function update(Request $request, string $user): RedirectResponse
    {
        $authModel = $this->authModelClass();
        $user = $this->findUser($user);

        $data = $request->validate([
            'role' => ['required', Rule::in($authModel::roles())],
            'guest_duration_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
        ]);

        if ($request->user()?->is($user) && $data['role'] !== $authModel::ROLE_ADMIN) {
            return redirect()
                ->route('accounts.index')
                ->with('accounts_error', 'You cannot remove admin access from your own account.');
        }

        if ($user->isAdmin() && $data['role'] !== $authModel::ROLE_ADMIN && $authModel::query()->where('role', $authModel::ROLE_ADMIN)->count() <= 1) {
            return redirect()
                ->route('accounts.index')
                ->with('accounts_error', 'At least one administrator account must remain active.');
        }

        $guestExpiry = $this->guestExpiryFromInput($data['role'], $data['guest_duration_hours'] ?? null);
        if ($guestExpiry instanceof RedirectResponse) {
            return $guestExpiry;
        }

        $user->forceFill([
            'role' => $data['role'],
            'guest_expires_at' => $guestExpiry,
            'is_blocked' => false,
            'blocked_at' => null,
        ])->save();

        return redirect()
            ->route('accounts.index')
            ->with('accounts_success', 'Account access updated successfully.');
    }

    public function toggleBlock(Request $request, string $user): RedirectResponse
    {
        $user = $this->findUser($user);

        if ($user->isPendingApproval()) {
            return redirect()
                ->route('accounts.index')
                ->with('accounts_error', 'Pending requests need to be approved or rejected instead of blocked.');
        }

        if ($request->user()?->is($user)) {
            return redirect()
                ->route('accounts.index')
                ->with('accounts_error', 'You cannot block your own account.');
        }

        if ($user->is_blocked) {
            if ($user->isGuest() && $user->hasExpiredGuestAccess()) {
                return redirect()
                    ->route('accounts.index')
                    ->with('accounts_error', 'Expired guest accounts need a new guest duration before reactivation.');
            }

            $user->forceFill([
                'is_blocked' => false,
                'blocked_at' => null,
            ])->save();

            return redirect()
                ->route('accounts.index')
                ->with('accounts_success', 'Account reactivated successfully.');
        }

        $user->forceFill([
            'is_blocked' => true,
            'blocked_at' => now(),
        ])->save();

        return redirect()
            ->route('accounts.index')
            ->with('accounts_success', 'Account blocked successfully.');
    }

    public function approve(Request $request, string $user, NotificationCenter $notifications): RedirectResponse
    {
        $user = $this->findUser($user);

        if (! $user->isPendingApproval()) {
            return redirect()
                ->route('accounts.index')
                ->with('accounts_error', 'Only pending requests can be approved.');
        }

        $user->forceFill([
            'account_status' => $this->authModelClass()::ACCOUNT_STATUS_ACTIVE,
            'requested_at' => $user->requested_at ?? now(),
            'approved_at' => now(),
            'rejected_at' => null,
            'is_blocked' => false,
            'blocked_at' => null,
        ])->save();

        $notifications->accountRequestApproved(
            (string) $user->name,
            (string) $user->email,
            (string) $user->getKey(),
        );

        return redirect()
            ->route('accounts.index')
            ->with('accounts_success', 'Account request approved successfully.');
    }

    public function reject(Request $request, string $user, NotificationCenter $notifications): RedirectResponse
    {
        $user = $this->findUser($user);

        if (! $user->isPendingApproval()) {
            return redirect()
                ->route('accounts.index')
                ->with('accounts_error', 'Only pending requests can be rejected.');
        }

        $notifications->accountRequestRejected(
            (string) $user->name,
            (string) $user->email,
            (string) $user->getKey(),
        );

        $user->delete();

        return redirect()
            ->route('accounts.index')
            ->with('accounts_success', 'Account request rejected successfully.');
    }

    public function destroy(Request $request, string $user): RedirectResponse
    {
        $authModel = $this->authModelClass();
        $user = $this->findUser($user);

        if ($request->user()?->is($user)) {
            return redirect()
                ->route('accounts.index')
                ->with('accounts_error', 'You cannot delete your own account.');
        }

        if ($user->isAdmin() && $authModel::query()->where('role', $authModel::ROLE_ADMIN)->count() <= 1) {
            return redirect()
                ->route('accounts.index')
                ->with('accounts_error', 'At least one administrator account must remain active.');
        }

        $user->delete();

        return redirect()
            ->route('accounts.index')
            ->with('accounts_success', 'Account deleted successfully.');
    }

    public function updateCurrentProfile(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return redirect()
            ->route('accounts.index')
            ->with('accounts_success', 'Your profile was updated successfully.');
    }

    public function updateCurrentPassword(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        return redirect()
            ->route('accounts.index')
            ->with('accounts_success', 'Your password was updated successfully.');
    }

    private function guestExpiryFromInput(string $role, ?int $durationHours): \DateTimeInterface|null|RedirectResponse
    {
        $authModel = $this->authModelClass();

        if ($role !== $authModel::ROLE_GUEST) {
            return null;
        }

        if ($durationHours === null) {
            return redirect()
                ->route('accounts.index')
                ->with('accounts_error', 'Guest accounts require an access duration in hours.');
        }

        return now()->addHours($durationHours);
    }

    /**
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    private function authModelClass(): string
    {
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $authModel */
        $authModel = config('auth.providers.users.model', \App\Models\User::class);

        return $authModel;
    }

    private function findUser(string $id): object
    {
        $authModel = $this->authModelClass();

        return $authModel::query()->findOrFail($id);
    }
}
