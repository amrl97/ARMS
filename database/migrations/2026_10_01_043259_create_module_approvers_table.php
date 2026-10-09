<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
                CREATE TABLE module_approvers (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    module_id INTEGER NOT NULL,
                    user_id INTEGER NULL,
                    role_id INTEGER NULL,
                    department_id INTEGER NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    FOREIGN KEY (module_id) REFERENCES access_module_configs(id) ON DELETE RESTRICT,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
                    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
                    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT,
                    CHECK (
                        (user_id IS NOT NULL AND role_id IS NULL AND department_id IS NULL)
                        OR (user_id IS NULL AND role_id IS NOT NULL)
                    )
                )
            SQL);

            return;
        }

        Schema::create('module_approvers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('access_module_configs')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('role_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE module_approvers
            ADD CONSTRAINT module_approvers_user_or_role_chk CHECK (
                (user_id IS NOT NULL AND role_id IS NULL AND department_id IS NULL)
                OR
                (user_id IS NULL AND role_id IS NOT NULL)
            )
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_approvers');
    }
};
