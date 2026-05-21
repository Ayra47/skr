<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE bookmarks DROP CONSTRAINT IF EXISTS bookmarks_user_id_bookmarkable_type_bookmarkable_id_unique');
        DB::statement('ALTER TABLE bookmarks ALTER COLUMN bookmarkable_id DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('UPDATE bookmarks SET bookmarkable_id = 0 WHERE bookmarkable_id IS NULL');
        DB::statement('ALTER TABLE bookmarks ALTER COLUMN bookmarkable_id SET NOT NULL');
        DB::statement('
            ALTER TABLE bookmarks
            ADD CONSTRAINT bookmarks_user_id_bookmarkable_type_bookmarkable_id_unique
            UNIQUE (user_id, bookmarkable_type, bookmarkable_id)
        ');
    }
};
