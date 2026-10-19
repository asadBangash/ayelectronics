<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InstallmentPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['id' => 308, 'name' => 'Installments_view'],
            ['id' => 309, 'name' => 'Installments_add'],
            ['id' => 310, 'name' => 'Installments_record_payment'],
            ['id' => 311, 'name' => 'Installments_settings'],
            ['id' => 312, 'name' => 'Installments_whatsapp'],
            ['id' => 313, 'name' => 'Installments_kyc_documents'],
            ['id' => 314, 'name' => 'Installments_export'],
        ];

        foreach ($permissions as $perm) {
            DB::table('permissions')->updateOrInsert(['id' => $perm['id']], ['name' => $perm['name']]);
            DB::table('permission_role')->updateOrInsert(
                ['permission_id' => $perm['id'], 'role_id' => 1],
                ['permission_id' => $perm['id'], 'role_id' => 1]
            );
        }

        $roles = [
            ['id' => 2, 'name' => 'Sales Manager', 'label' => 'Sales Manager', 'status' => 1, 'description' => 'Installment sales oversight'],
            ['id' => 3, 'name' => 'Sales Staff', 'label' => 'Sales Staff', 'status' => 1, 'description' => 'Installment sales entry'],
            ['id' => 4, 'name' => 'Cashier', 'label' => 'Cashier', 'status' => 1, 'description' => 'Payment collection'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(['id' => $role['id']], $role);
        }

        $managerPerms = [308, 309, 310, 312, 313, 314];
        $staffPerms = [308, 309, 312, 313];
        $cashierPerms = [308, 310, 313];

        foreach ($managerPerms as $pid) {
            DB::table('permission_role')->updateOrInsert(
                ['permission_id' => $pid, 'role_id' => 2],
                ['permission_id' => $pid, 'role_id' => 2]
            );
        }
        foreach ($staffPerms as $pid) {
            DB::table('permission_role')->updateOrInsert(
                ['permission_id' => $pid, 'role_id' => 3],
                ['permission_id' => $pid, 'role_id' => 3]
            );
        }
        foreach ($cashierPerms as $pid) {
            DB::table('permission_role')->updateOrInsert(
                ['permission_id' => $pid, 'role_id' => 4],
                ['permission_id' => $pid, 'role_id' => 4]
            );
        }
    }
}
