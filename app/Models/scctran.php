<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class scctran extends Model
{
    protected $connection = 'DATA_MYSQL';

    protected $table = 'scctran';

    protected $primaryKey = 'urut';

    public $timestamps = false;

    public $incrementing = false;

    protected $guarded = [];
}
