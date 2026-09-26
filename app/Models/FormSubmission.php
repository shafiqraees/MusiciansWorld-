<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormSubmission extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['data' => 'array', 'event_date' => 'date'];
    }
}
