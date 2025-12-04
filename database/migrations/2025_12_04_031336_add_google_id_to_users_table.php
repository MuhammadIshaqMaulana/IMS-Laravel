<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Kolom untuk menyimpan ID unik Google
            // Nullable karena user lama mungkin tidak punya Google ID
            $table->string('google_id')->nullable()->unique()->after('email');

            // Kolom untuk menunjukkan apakah pengguna ini adalah admin
            $table->boolean('is_admin')->default(false)->after('google_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('google_id');
            $table->dropColumn('is_admin');
        });
    }
};
