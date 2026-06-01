<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $guard = 'web';

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view products',
            'create products',
            'edit products',
            'delete products',
            'view orders',
            'create orders',
            'update orders',
            'cancel orders',
            'view users',
            'edit users',
            'view deliveries',
            'update delivery status',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, $guard);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Admin role and assign all permissions
        $adminRole = Role::findOrCreate('admin', $guard);
        $adminRole->givePermissionTo([
            'view products',
            'create products',
            'edit products',
            'delete products',
            'view orders',
            'update orders',
            'cancel orders',
            'view users',
            'edit users',
            'view deliveries',
        ]);

        // Create Customer role with limited permissions
        $customerRole = Role::findOrCreate('customer', $guard);
        $customerRole->givePermissionTo([
            'view products',
            'view orders',
            'create orders',
            'cancel orders'
        ]);

        // Create Delivery role
        $deliveryRole = Role::findOrCreate('delivery', $guard);
        $deliveryRole->givePermissionTo([
            'view deliveries',
            'update delivery status',
            'view orders',
            'view products'
        ]);
    }
}
