# File: api.py
# Microservice API Sederhana menggunakan Flask

from flask import Flask, request, jsonify
from flask_cors import CORS
import re

app = Flask(__name__)
# Mengizinkan permintaan dari semua origin (diperlukan saat development)
CORS(app)

# --- Endpoint 1: Cek Status API ---
@app.route('/api/v1/status', methods=['GET'])
def get_status():
    """Mengembalikan status hidup API."""
    return jsonify({
        "status": "OK",
        "message": "API Toko Roti Python berjalan dengan baik."
    })

# --- Endpoint 2: Validasi Format SKU Lanjutan ---
@app.route('/api/v1/validasi-sku', methods=['POST'])
def validasi_sku():
    """
    Menerima data SKU dan melakukan validasi format.
    Format yang diharapkan: Tiga huruf produk (misalnya ROT)-Tiga huruf bahan utama (misalnya TEP)-Nomor Batch (4 digit)
    Contoh: ROT-TEP-1234
    """
    try:
        data = request.get_json()
        sku = data.get('sku', '')

        # Regex: [A-Z]{3} (3 huruf besar) - [A-Z]{3} (3 huruf besar) - [0-9]{4} (4 digit angka)
        pattern = r"^[A-Z]{3}-[A-Z]{3}-\d{4}$"

        is_valid = re.match(pattern, sku) is not None

        if is_valid:
            return jsonify({
                "sku": sku,
                "is_valid": True,
                "pesan": "Format SKU valid sesuai standar manufaktur."
            }), 200
        else:
            return jsonify({
                "sku": sku,
                "is_valid": False,
                "pesan": "Format SKU TIDAK VALID. Harus mengikuti format: PRD-BAH-1234."
            }), 200

    except Exception as e:
        return jsonify({
            "status": "Error",
            "pesan": f"Kesalahan pemrosesan: {str(e)}"
        }), 500

if __name__ == '__main__':
    # DIUBAH: Jalankan API di port 8001
    app.run(debug=True, port=8001)
