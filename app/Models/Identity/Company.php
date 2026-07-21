<?php

namespace App\Models\Identity;

use App\Support\Auditing\Auditable;
use App\Support\PreventsPhysicalDeletion;
use Database\Factories\Identity\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use Auditable, HasFactory, PreventsPhysicalDeletion;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'logo_path',
    ];

    protected static function booted(): void
    {
        static::creating(function (): void {
            if (static::query()->exists()) {
                throw new LogicException('Une fiche entreprise existe déjà.');
            }
        });
    }
}
