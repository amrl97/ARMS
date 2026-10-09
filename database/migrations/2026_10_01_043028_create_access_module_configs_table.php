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
                CREATE TABLE access_module_configs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    module_name VARCHAR(255) NOT NULL UNIQUE,
                    max_approvers INTEGER NOT NULL DEFAULT 0,
                    required_approvals INTEGER NOT NULL DEFAULT 0,
                    is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    CHECK (max_approvers >= 0),
                    CHECK (required_approvals <= max_approvers),
                    CHECK (
                        (max_approvers = 0 AND required_approvals = 0)
                        OR (max_approvers >= 1 AND required_approvals = 1)
                    )
                )
            SQL);

            return;
        }

        Schema::create('access_module_configs', function (Blueprint $table) {
            $table->id();
            $table->string('module_name')->unique();
            $table->integer('max_approvers')->default(0);
            $table->integer('required_approvals')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE access_module_configs
        ADD CONSTRAINT access_module_configs_max_approvers_chk
        CHECK (max_approvers >= 0)
        ');

        DB::statement('ALTER TABLE access_module_configs
        ADD CONSTRAINT access_module_configs_is_active_chk
        CHECK (is_active IN (0, 1))
        ');

        DB::statement('ALTER TABLE access_module_configs
        ADD CONSTRAINT access_module_configs_required_lte_max_chk
        CHECK (required_approvals <= max_approvers)
        ');

        DB::statement('ALTER TABLE access_module_configs
        ADD CONSTRAINT access_module_configs_approvals_chk
        CHECK ((max_approvers = 0 AND required_approvals = 0)
        OR (max_approvers >= 1 AND required_approvals = 1))
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_module_configs');
    }
};