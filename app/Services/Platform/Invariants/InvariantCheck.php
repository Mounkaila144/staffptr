<?php

namespace App\Services\Platform\Invariants;

interface InvariantCheck
{
    public function check(): InvariantResult;
}
