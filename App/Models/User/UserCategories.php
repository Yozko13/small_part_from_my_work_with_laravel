<?php

namespace App\Models\User;

use App\Models\Shop\Category;
use App\Models\Shop\SubCategory;
use Illuminate\Database\Eloquent\Model;

class UserCategories extends Model
{
    public function category() {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    public function subCategory() {
        return $this->belongsTo(SubCategory::class, 'sub_category_id', 'id');
    }
}
