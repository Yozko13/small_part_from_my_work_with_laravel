<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    public function formatPrice($price) {
        switch ($this->symbol_position) {
            case 'front':
                return $this->symbol . number_format($price, 2, ',', '.');
                break;
            case 'back':
                return number_format($price, 2, '.', ' ') . $this->symbol;
                break;
        }
    }
}
