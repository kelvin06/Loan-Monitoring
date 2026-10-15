<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DownpaymentRate extends Model
{
    protected $fillable = ['name', 'rate'];

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }
}
