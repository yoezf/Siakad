<?php

namespace App\Modules\Impor;

use App\Models\Student;

class ImporSiswa extends Importer
{
    protected function kolom(): array
    {
        return [
            'nama' => ['key' => 'name', 'label' => 'nama', 'wajib' => true],
            'nisn' => ['key' => 'nisn', 'label' => 'nisn', 'teks' => true],
            'nis' => ['key' => 'nis', 'label' => 'nis', 'teks' => true],
            'jenis kelamin' => ['key' => 'gender', 'label' => 'jenis kelamin', 'wajib' => true],
            'tempat lahir' => ['key' => 'birth_place', 'label' => 'tempat lahir'],
            'tanggal lahir' => ['key' => 'birth_date', 'label' => 'tanggal lahir'],
        ];
    }

    protected function aturan(): array
    {
        return [
            'name' => 'required|max:255',
            'nisn' => 'nullable|digits:10',
            'nis' => 'nullable|max:30',
            'gender' => 'required|in:L,P',
            'birth_place' => 'nullable|max:255',
            'birth_date' => 'nullable|date_format:Y-m-d|after:1900-01-01|before_or_equal:today',
        ];
    }

    protected function kunciUnik(): array
    {
        return ['nisn', 'nis'];
    }

    protected function modelUnik(): string
    {
        return Student::class;
    }

    protected function normalisasi(string $key, string $nilai): string
    {
        if ($key !== 'gender') {
            return $nilai;
        }

        return match (mb_strtolower($nilai)) {
            'l', 'lk', 'laki-laki', 'laki laki', 'pria' => 'L',
            'p', 'pr', 'perempuan', 'wanita' => 'P',
            default => $nilai,
        };
    }

    protected function simpan(array $row): int
    {
        return Student::create(array_map(fn ($v) => $v === '' ? null : $v, $row))->getKey();
    }

    protected function contoh(): array
    {
        return ['Budi Santoso', '0012345678', '1001', 'L', 'Bandung', '2015-03-21'];
    }
}
