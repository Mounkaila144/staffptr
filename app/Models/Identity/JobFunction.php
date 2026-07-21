<?php

namespace App\Models\Identity;

use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Database\Factories\Identity\JobFunctionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobFunction extends Model
{
    /** @use HasFactory<JobFunctionFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = ['name', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
