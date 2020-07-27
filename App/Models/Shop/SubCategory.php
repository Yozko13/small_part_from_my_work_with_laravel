<?php

namespace App\Models\Shop;

use App\Models\User\UserCategories;
use Illuminate\Database\Eloquent\Model;
use Cviebrock\EloquentSluggable\Sluggable;
use Cviebrock\EloquentSluggable\SluggableScopeHelpers;

class SubCategory extends Model
{
    use Sluggable;
    use SluggableScopeHelpers;

    /**
     * Return the sluggable configuration array for this model.
     *
     * @return array
     */
    public function sluggable()
    {
        return [
            'slug' => [
                'source' => 'name'
            ]
        ];
    }

    public function category() {
        return $this->belongsTo(Category::class);
    }

    public function userSubCategory() {
        return $this->hasMany(UserCategories::class, 'id', 'sub_category_id');
    }

    public function subCategoryAttributes() {
        return$this->hasMany(SubCategoryAttribute::class);
    }
    
    public function listings() {
        return $this->hasMany(Listing::class);
    }
    
    public function getLink() {
        return '/'.$this->category->slug . '/' . $this->slug;
    }
    
    public function getName() {
        return $this->name;
    }
    
    public function getTag() {
        return $this->tag;
    }  

    public function getUrl() {
        return url("{$this->getLink()}");
    }

    public function listingsCount() {
        return $this->hasMany(Listing::class)
            ->selectRaw('sub_category_id, count(*) as aggregate')
            ->where('active', true)
            ->groupBy('sub_category_id');
    }
}
