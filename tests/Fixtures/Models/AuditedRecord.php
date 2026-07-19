<?php

namespace Tests\Fixtures\Models;

use App\Support\Auditing\Auditable;
use Illuminate\Database\Eloquent\Model;

class AuditedRecord extends Model
{
    use Auditable;

    /** @var list<string> */
    protected $fillable = ['name'];
}
