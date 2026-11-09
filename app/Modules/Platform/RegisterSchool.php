<?php

namespace App\Modules\Platform;

use App\Models\School;
use App\Models\User;
use App\Support\Peran;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Mendaftarkan sekolah baru beserta akun admin pertamanya, dalam satu transaksi. */
class RegisterSchool
{
    /**
     * @param  array{name:string, city?:?string, plan:string, features?:array, status?:string, admin_name:string, admin_email:string, admin_password:string}  $data
     */
    public function handle(array $data): School
    {
        if (! array_key_exists($data['plan'], config('fitur.paket'))) {
            throw new InvalidArgumentException("Paket tidak dikenal: {$data['plan']}");
        }

        return DB::transaction(function () use ($data) {
            $school = School::create([
                'name' => $data['name'],
                'city' => $data['city'] ?? null,
                'plan' => $data['plan'],
                'features' => $data['features'] ?? null, // null: ikut paket
                'status' => $data['status'] ?? 'aktif',
            ]);

            $admin = new User(['name' => $data['admin_name'], 'email' => $data['admin_email'], 'password' => $data['admin_password']]);
            $admin->school_id = $school->id;
            $admin->save();
            $admin->assignRole(Peran::ADMIN_SEKOLAH);

            return $school;
        });
    }
}
