<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Validate the old schema before making any changes.
        if (
            ! Schema::hasColumn('roles', 'slug') ||
            Schema::hasColumn('roles', 'role_code') ||
            ! Schema::hasColumn('roles', 'permissions') ||
            Schema::hasTable('role_permissions')
        ) {
            throw new RuntimeException(
                'Unexpected roles schema. Migration stopped.'
            );
        }

        // Validate all existing permission JSON before changing the schema.
        $roles = DB::table('roles')->get(['id', 'slug', 'permissions']);
        $permissionsByRole = [];
        $itManagerId = null;

        foreach ($roles as $role) {
            $decoded = json_decode($role->permissions ?? '[]', true);

            if (! is_array($decoded)) {
                throw new RuntimeException(
                    "Invalid permissions JSON for role ID {$role->id}."
                );
            }

            $permissionsByRole[$role->id] = array_values(
                array_unique(array_filter($decoded, 'is_string'))
            );

            if ($role->slug === 'it_manager') {
                $itManagerId = $role->id;
            }
        }

        // A fresh database may not have seeded roles yet.
        // The RoleSeeder will assign default permissions after migrations.

        // Rename the role code column.
        Schema::table('roles', function (Blueprint $table) {
            $table->renameColumn('slug', 'role_code');
        });

        // Create the normalized permission table.
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')
                ->constrained('roles')
                ->cascadeOnDelete();
            $table->string('permission', 100);
            $table->timestamps();

            $table->unique(['role_id', 'permission']);
        });

        // Move the existing permissions into individual rows.
        foreach ($permissionsByRole as $roleId => $codes) {
            foreach ($codes as $code) {
                DB::table('role_permissions')->insert([
                    'role_id' => $roleId,
                    'permission' => $code,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Ensure the IT manager has the inventory and reservation permissions.
        if ($itManagerId !== null) {
            foreach (['parts.manage', 'reservations.manage'] as $permission) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $itManagerId,
                    'permission' => $permission,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Drop the old JSON column after the data has been migrated.
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }

    public function down(): void
    {
        if (
            ! Schema::hasColumn('roles', 'role_code') ||
            ! Schema::hasTable('role_permissions')
        ) {
            throw new RuntimeException(
                'Unexpected role permission schema. Rollback stopped.'
            );
        }

        // Restore the old JSON column.
        Schema::table('roles', function (Blueprint $table) {
            $table->json('permissions')->nullable()->after('is_system');
        });

        // Rebuild the JSON permission arrays.
        foreach (DB::table('roles')->get(['id']) as $role) {
            $codes = DB::table('role_permissions')
                ->where('role_id', $role->id)
                ->orderBy('id')
                ->pluck('permission')
                ->all();

            DB::table('roles')
                ->where('id', $role->id)
                ->update([
                    'permissions' => json_encode(
                        $codes,
                        JSON_UNESCAPED_UNICODE
                    ),
                ]);
        }

        Schema::dropIfExists('role_permissions');

        // Restore the old role code column name.
        Schema::table('roles', function (Blueprint $table) {
            $table->renameColumn('role_code', 'slug');
        });
    }
};