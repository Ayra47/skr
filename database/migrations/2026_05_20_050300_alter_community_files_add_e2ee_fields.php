<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_files', function (Blueprint $table) {
            // E2EE-ready storage fields
            $table->text('storage_key')->nullable()->after('path');
            $table->text('encrypted_filename')->nullable()->after('storage_key');
            $table->string('mime_bucket', 30)->nullable()->after('mime');
            $table->unsignedBigInteger('size_bytes')->nullable()->after('size');
            $table->string('checksum_sha256', 64)->nullable()->after('size_bytes');

            $table->uuid('key_epoch_id')->nullable()->after('checksum_sha256');
            $table->foreign('key_epoch_id')->references('id')->on('community_key_epochs')->nullOnDelete();

            $table->timestamp('expires_at', 0)->nullable()->after('key_epoch_id');
            $table->timestamp('blob_deleted_at', 0)->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('community_files', function (Blueprint $table) {
            $table->dropForeign(['key_epoch_id']);
            $table->dropColumn([
                'storage_key', 'encrypted_filename', 'mime_bucket', 'size_bytes',
                'checksum_sha256', 'key_epoch_id', 'expires_at', 'blob_deleted_at',
            ]);
        });
    }
};
