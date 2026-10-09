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
                CREATE TABLE access_request_approvals (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    request_detail_id INTEGER NOT NULL,
                    step VARCHAR(255) NOT NULL,
                    approver_id INTEGER NOT NULL,
                    rejection_reason TEXT NULL,
                    status VARCHAR(255) NOT NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    FOREIGN KEY (request_detail_id) REFERENCES access_request_details(id) ON DELETE RESTRICT,
                    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE RESTRICT,
                    UNIQUE (request_detail_id, approver_id),
                    CHECK (step IN ('module', 'final')),
                    CHECK (status IN ('pending', 'approved', 'rejected', 'skipped'))
                )
            SQL);

            return;
        }

        Schema::create('access_request_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_detail_id')->constrained('access_request_details')->restrictOnDelete();
            $table->string('step');
            $table->foreignId('approver_id')->constrained('users')->restrictOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->string('status');
            $table->timestamps();
            $table->unique(['request_detail_id', 'approver_id']);
        });

        DB::statement("ALTER TABLE access_request_approvals
            ADD CONSTRAINT access_request_approvals_step_chk
            CHECK (step IN ('module', 'final'))
        ");

        DB::statement("ALTER TABLE access_request_approvals
            ADD CONSTRAINT access_request_approvals_status_chk
            CHECK (status IN ('pending', 'approved', 'rejected', 'skipped'))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_request_approvals');
    }
};
