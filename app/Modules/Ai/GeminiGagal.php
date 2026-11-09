<?php

namespace App\Modules\Ai;

use RuntimeException;

/** Kegagalan pemanggilan Gemini. Pesannya aman ditampilkan: tidak memuat kunci API maupun isi respons mentah. */
final class GeminiGagal extends RuntimeException
{
    public function __construct(string $pesan, public readonly bool $bisaDiulang = false)
    {
        parent::__construct($pesan);
    }
}
