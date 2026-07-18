<?php

use Illuminate\Database\Schema\Blueprint;

return static function (Blueprint $table): void {
    $table->decimal('total_amount', 15, 2);
};
