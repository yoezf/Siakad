<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class SchoolScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $id = CurrentSchool::id();
        // Tanpa sekolah aktif: kembalikan kosong, bukan semua data.
        $id === null
            ? $builder->whereRaw('1 = 0')
            : $builder->where($model->qualifyColumn('school_id'), $id);
    }
}
