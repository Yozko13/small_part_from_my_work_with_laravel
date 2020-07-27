<?php

namespace App\Models\Shop;

use App\Models\User\UserCategories;
use Illuminate\Database\Eloquent\Model;
use Cviebrock\EloquentSluggable\Sluggable;
use Cviebrock\EloquentSluggable\SluggableScopeHelpers;

class Category extends Model
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

    public function subCategories() {
        return $this->hasMany(SubCategory::class);
    }

    public function subCategoriesCount() {
        return $this->hasMany(SubCategory::class)->count();
    }

    public function subCategoryAttributes() {
        return$this->hasMany(SubCategoryAttribute::class);
    }
    
    public function listings() {
        return $this->hasMany(Listing::class);
    }

    public function userCategory() {
        return $this->hasMany(UserCategories::class, 'id', 'category_id');
    }
    
    public function listingsCount() {
        return $this->hasMany(Listing::class)
            ->selectRaw('category_id, count(*) as aggregate')
            ->where('active', true)
            ->groupBy('category_id');
    }
    
    public function getName() {
        return $this->name;
    }
    
    public function getLink() {
        return '/'.$this->slug;
    }

    public function getUrl() {
        return url("{$this->getLink()}");
    }
    
}
