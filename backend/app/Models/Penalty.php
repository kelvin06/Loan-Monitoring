<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Penalty extends Model
{
    protected $fillable = ['name', 'rate', 'penalty_option', 'description'];

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }
}
