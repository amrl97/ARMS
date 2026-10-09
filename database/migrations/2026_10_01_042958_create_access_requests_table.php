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
                CREATE TABLE access_requests (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    requested_by INTEGER NOT NULL,
                    internal_beneficiary INTEGER NULL,
                    external_beneficiary VARCHAR(255) NULL,
                    remarks TEXT NULL,
                    status VARCHAR(255) NOT NULL DEFAULT 'pending',
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE RESTRICT,
                    FOREIGN KEY (internal_beneficiary) REFERENCES users(id) ON DELETE RESTRICT,
                    CHECK (internal_beneficiary IS NULL OR internal_beneficiary <> requested_by),
                    CHECK (internal_beneficiary IS NULL OR external_beneficiary IS NULL),
                    CHECK (external_beneficiary IS NULL OR length(trim(external_beneficiary)) > 0),
                    CHECK (status IN ('pending', 'in_progress', 'rejected', 'partially_approved', 'approved', 'cancelled'))
                )
            SQL);

            return;
        }

        Schema::create('access_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('internal_beneficiary')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('external_beneficiary')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE access_requests
            ADD CONSTRAINT access_requests_internal_beneficiary_not_requester_chk
            CHECK (internal_beneficiary IS NULL OR internal_beneficiary <> requested_by)
        ');

        DB::statement('ALTER TABLE access_requests
            ADD CONSTRAINT access_requests_single_beneficiary_chk
            CHECK (internal_beneficiary IS NULL OR external_beneficiary IS NULL)
        ');

        DB::statement('ALTER TABLE access_requests
            ADD CONSTRAINT access_requests_external_beneficiary_nonempty_chk
            CHECK (external_beneficiary IS NULL OR CHAR_LENGTH(TRIM(external_beneficiary)) > 0)
        ');

        DB::statement("ALTER TABLE access_requests
            ADD CONSTRAINT access_requests_status_chk
            CHECK (status IN ('pending', 'in_progress', 'rejected', 'partially_approved', 'approved', 'cancelled'))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_requests');
    }
};
