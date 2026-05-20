<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('items', function (Blueprint $table) {
            // Path untuk menyimpan silsilah (Cth: /1/5/12/)
            $table->string('path', 500)->nullable()->after('folder_id')->index();
        });
    }

    public function down(): void {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('path');
        });
    }
};
