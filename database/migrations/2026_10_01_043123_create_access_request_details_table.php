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
                CREATE TABLE access_request_details (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    module_id INTEGER NOT NULL,
                    request_id INTEGER NOT NULL,
                    access_type VARCHAR(255) NOT NULL,
                    priority VARCHAR(255) NOT NULL,
                    justification TEXT NULL,
                    status VARCHAR(255) NOT NULL DEFAULT 'pending',
                    start_date DATE NULL,
                    end_date DATE NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    FOREIGN KEY (module_id) REFERENCES access_module_configs(id) ON DELETE RESTRICT,
                    FOREIGN KEY (request_id) REFERENCES access_requests(id) ON DELETE RESTRICT,
                    UNIQUE (request_id, module_id),
                    CHECK (access_type IN ('New Staff', 'Privilege', 'Temporary', 'Vendor/3rd Party')),
                    CHECK (priority IN ('Low', 'Medium', 'High')),
                    CHECK (status IN ('pending', 'approved', 'rejected', 'expired', 'revoked', 'cancelled'))
                )
            SQL);

            return;
        }

        Schema::create('access_request_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('access_module_configs')->restrictOnDelete();
            $table->foreignId('request_id')->constrained('access_requests')->restrictOnDelete();
            $table->string('access_type');
            $table->string('priority');
            $table->text('justification')->nullable();
            $table->string('status')->default('pending');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
            $table->unique(['request_id', 'module_id']);
        });

        DB::statement("ALTER TABLE access_request_details
            ADD CONSTRAINT access_request_details_access_type_chk
            CHECK (access_type IN ('New Staff', 'Privilege', 'Temporary', 'Vendor/3rd Party'))
        ");

        DB::statement("ALTER TABLE access_request_details
            ADD CONSTRAINT access_request_details_priority_chk
            CHECK (priority IN ('Low', 'Medium', 'High'))
        ");

        DB::statement("ALTER TABLE access_request_details
            ADD CONSTRAINT access_request_details_status_chk
            CHECK (status IN ('pending', 'approved', 'rejected', 'expired', 'revoked', 'cancelled'))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_request_details');
    }
};
