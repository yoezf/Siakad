<?php

namespace App\Modules\Impor;

use App\Models\Staff;

class ImporStaf extends Importer
{
    protected function kolom(): array
    {
        return [
            'nama' => ['key' => 'name', 'label' => 'nama', 'wajib' => true],
            'nip' => ['key' => 'nip', 'label' => 'nip', 'teks' => true],
            'jenis' => ['key' => 'kind', 'label' => 'jenis', 'wajib' => true],
            'telepon' => ['key' => 'phone', 'label' => 'telepon', 'teks' => true],
            'email' => ['key' => 'email', 'label' => 'email'],
        ];
    }

    protected function aturan(): array
    {
        return [
            'name' => 'required|max:255',
            'nip' => 'nullable|max:30',
            'kind' => 'required|in:guru,tendik',
            'phone' => 'nullable|max:30',
            'email' => 'nullable|email|max:255',
        ];
    }

    protected function kunciUnik(): array
    {
        return ['nip'];
    }

    protected function modelUnik(): string
    {
        return Staff::class;
    }

    protected function normalisasi(string $key, string $nilai): string
    {
        if ($key !== 'kind') {
            return $nilai;
        }

        return match (mb_strtolower($nilai)) {
            'guru' => 'guru',
            'tendik', 'tenaga kependidikan', 'staf', 'staff' => 'tendik',
            default => $nilai,
        };
    }

    protected function simpan(array $row): int
    {
        return Staff::create(array_map(fn ($v) => $v === '' ? null : $v, $row))->getKey();
    }

    protected function contoh(): array
    {
        return ['Siti Aminah', '198001012005012001', 'guru', '081234567890', 'siti@contoh.test'];
    }
}
