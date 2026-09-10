<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(["name", "description"])]
class Category extends Model
{
    use SoftDeletes, HasFactory;

    public function events()
    {
        return $this->hasMany(Event::class);
    }
}
