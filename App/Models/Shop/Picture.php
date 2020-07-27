<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class Picture extends Model
{
    public function getListing() {
        return $this->belongsTo(Listing::class);
    }

    public function getPublicAddress() {
        return Config::get('constants.paths.listing_images_url') . $this->name;
    }

    public function getDirectoryAddress() {
        return Config::get('constants.paths.listing_images_path') . $this->name;
    }

    public function checkIfSameListing($listing_id) {
        return $this->listing_id == $listing_id;
    }

    public function deleteFileFromServer() {
        if(file_exists($this->getDirectoryAddress())) {
            return unlink($this->getDirectoryAddress());
        } else {
            return 'Файлът не е намерен.';
        }
    }
}
