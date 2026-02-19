<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Translation extends Model
{
    protected $fillable = ['group', 'key', 'en', 'ru', 'lv'];

    public function get(string $locale): ?string
    {
        $value = $this->getAttribute($locale);
        if ($value !== null && $value !== '') {
            return $value;
        }
        return $this->en;
    }
}
