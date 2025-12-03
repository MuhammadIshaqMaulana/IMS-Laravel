<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ApiPythonController extends Controller
{
    // DIUBAH: Alamat API Python diubah ke port 8001
    private $pythonApiUrl = 'http://127.0.0.1:8001/api/v1/';

    /**
     * Menampilkan status API Python dan form pengujian validasi.
     */
    public function index()
    {
        $status = $this->checkApiStatus();

        // Mengarahkan ke views/api_python/index.blade.php
        return view('api_python.index', compact('status'));
    }

    /**
     * Fungsi helper untuk mengecek status API Python.
     */
    private function checkApiStatus()
    {
        try {
            // Mengirim request GET ke http://127.0.0.1:8000/api/v1/status
            $response = Http::get($this->pythonApiUrl . 'status');

            if ($response->successful()) {
                return [
                    'online' => true,
                    'status_message' => $response->json()['message']
                ];
            } else {
                return [
                    'online' => false,
                    'status_message' => 'API Python merespons dengan kesalahan (HTTP Code: ' . $response->status() . ')'
                ];
            }
        } catch (\Exception $e) {
            // Gagal terhubung (misal, server Python belum berjalan)
            return [
                'online' => false,
                'status_message' => 'Gagal terhubung ke API Python di ' . $this->pythonApiUrl . '. Pastikan API berjalan di port 8000.'
            ];
        }
    }

    /**
     * Mengirim permintaan POST untuk validasi SKU ke API Python.
     */
    public function validasiSku(Request $request)
    {
        $request->validate(['sku_input' => 'required|string|max:50']);

        try {
            // Mengirim request POST ke http://127.0.0.1:8000/api/v1/validasi-sku
            $response = Http::post($this->pythonApiUrl . 'validasi-sku', [
                'sku' => $request->sku_input // Mengirim data 'sku' ke Python
            ]);

            if ($response->successful() && ($response->json()['status'] ?? null) != 'Error') {
                $result = $response->json();
                $result['status_code'] = $response->status();
                return redirect()->route('api-python.index')->with('api_result', $result);
            } else {
                 return redirect()->route('api-python.index')->with('api_error', 'Kesalahan API: ' . ($response->json()['pesan'] ?? 'Respon tidak valid.') );
            }
        } catch (\Exception $e) {
            return redirect()->route('api-python.index')->with('api_error', 'Kesalahan koneksi ke Python: ' . $e->getMessage());
        }
    }
}
