<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InstallmentPolicy
{
    use HandlesAuthorization;

    protected function allows(User $user, string $permissionName): bool
    {
        $permission = Permission::where('name', $permissionName)->first();
        if (! $permission) {
            return false;
        }

        return $user->hasRole($permission->roles);
    }

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'Installments_view');
    }

    public function view(User $user): bool
    {
        return $this->allows($user, 'Installments_view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'Installments_add');
    }

    public function recordPayment(User $user): bool
    {
        return $this->allows($user, 'Installments_record_payment');
    }

    public function manageSettings(User $user): bool
    {
        return $this->allows($user, 'Installments_settings');
    }

    public function sendWhatsapp(User $user): bool
    {
        return $this->allows($user, 'Installments_whatsapp');
    }

    public function viewDocuments(User $user): bool
    {
        return $this->allows($user, 'Installments_kyc_documents');
    }

    public function export(User $user): bool
    {
        return $this->allows($user, 'Installments_export');
    }
}
