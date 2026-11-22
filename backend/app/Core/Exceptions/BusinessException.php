<?php

namespace App\Core\Exceptions;

use RuntimeException;

/**
 * Pelanggaran aturan bisnis (mis. stok tidak cukup, dokumen sudah dikonfirmasi).
 * Otomatis dikirim ke klien sebagai HTTP 422 dengan pesan apa adanya.
 */
class BusinessException extends RuntimeException {}
