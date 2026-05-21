<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_device_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // Human-readable device label (e.g. "iPhone 15 Pro")
            $table->string('device_label', 100)->nullable();

            // Client-generated device identifier (stable per install)
            $table->string('device_identifier', 64);

            // ECDH/X25519 public key — base64url encoded
            $table->text('public_key');

            // Fingerprint for verification UI (e.g. SHA-256 of public_key, hex)
            $table->string('fingerprint', 64);

            $table->timestamp('last_seen_at', 0)->nullable();

            // Soft-revoke instead of hard-delete to preserve audit trail
            $table->timestamp('revoked_at', 0)->nullable();

            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
        });

        DB::statement('CREATE INDEX user_device_keys_user_id_idx ON user_device_keys (user_id)');
        DB::statement('CREATE UNIQUE INDEX user_device_keys_user_identifier_unique ON user_device_keys (user_id, device_identifier)');
        DB::statement('CREATE INDEX user_device_keys_active_idx ON user_device_keys (user_id) WHERE revoked_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_device_keys');
    }
};
