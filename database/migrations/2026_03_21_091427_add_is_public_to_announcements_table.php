<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            if (! Schema::hasColumn('announcements', 'is_public')) {
                $table->boolean('is_public')->default(false)->after('is_pinned');
                $table->index('is_public');
            }
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropIndex(['is_public']);
            $table->dropColumn('is_public');
        });
    }
};
