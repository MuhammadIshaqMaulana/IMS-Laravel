<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Matikan Foreign Key Check agar proses pembersihan lancar
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // 1. Buat tabel folders murni
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('path', 500)->nullable()->index(); // Materialized Path
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('folders')->onDelete('cascade');
        });

        // 2. DATA MIGRATION
        // Ambil semua folder lama dari tabel items
        $oldFolders = DB::table('items')->where('tags', 'like', '%"folder"%')->get();
        $folderMapping = [];
        $oldFolderIds = $oldFolders->pluck('id')->toArray();

        foreach ($oldFolders as $old) {
            $newId = DB::table('folders')->insertGetId([
                'nama' => $old->nama,
                'parent_id' => null,
                'created_at' => $old->created_at,
                'updated_at' => $old->updated_at,
            ]);
            $folderMapping[$old->id] = $newId;
        }

        // Sinkronkan parent_id di tabel folders
        foreach ($oldFolders as $old) {
            if ($old->folder_id && isset($folderMapping[$old->folder_id])) {
                DB::table('folders')->where('id', $folderMapping[$old->id])->update([
                    'parent_id' => $folderMapping[$old->folder_id]
                ]);
            }
        }

        // 3. Siapkan kolom folder_id baru di items
        Schema::table('items', function (Blueprint $table) {
            $table->unsignedBigInteger('new_folder_id')->nullable()->after('folder_id');
        });

        // Mapping folder_id item ke ID folder baru di tabel folders
        $itemsToMigrate = DB::table('items')->whereNotNull('folder_id')->get();
        foreach ($itemsToMigrate as $item) {
            if (isset($folderMapping[$item->folder_id])) {
                DB::table('items')->where('id', $item->id)->update([
                    'new_folder_id' => $folderMapping[$item->folder_id]
                ]);
            }
        }

        // 4. CLEANUP (FORCE DELETE DATA DUMMY)
        // Hapus semua transaksi yang merujuk ke ID folder lama
        if (!empty($oldFolderIds)) {
            DB::table('transaksis')->whereIn('item_id', $oldFolderIds)->delete();
        }

        // Hapus data folder lama di items secara permanen
        DB::table('items')->where('tags', 'like', '%"folder"%')->delete();

        // 5. RESTRUKTURISASI TABEL ITEMS
        Schema::table('items', function (Blueprint $table) {
            // Drop Foreign Key lama dengan aman
            try {
                $table->dropForeign(['folder_id']);
                $table->dropForeign(['parent_id']);
            } catch (\Exception $e) {}

            // Hapus kolom lama
            $table->dropColumn(['folder_id', 'parent_id', 'path']);

            // Rename kolom baru ke nama standar
            $table->renameColumn('new_folder_id', 'folder_id');
        });

        Schema::table('items', function (Blueprint $table) {
            // Tambahkan Foreign Key ke tabel folders yang baru
            $table->foreign('folder_id')->references('id')->on('folders')->onDelete('set null');
        });

        // Aktifkan kembali Foreign Key Check
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['folder_id']);
        });
        Schema::dropIfExists('folders');
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
};
