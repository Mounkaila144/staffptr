<?php

namespace App\Support;

use LogicException;

trait PreventsPhysicalDeletion
{
    /**
     * Identity records are retained permanently; lifecycle changes are expressed through state.
     */
    public function delete(): never
    {
        throw new LogicException('La suppression physique de cette ressource est interdite.');
    }
}
