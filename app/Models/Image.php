<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A photo kept inside the database (product photo or payment confirmation).
 * The photo bytes (image_data) are never loaded with the product or the sale; the page asks for them
 * separately through the /images/{id} link, so lists stay fast.
 */
class Image extends Model
{
    protected $fillable = ['file_name', 'mime_type', 'file_size', 'image_data'];

    protected $hidden = ['image_data'];
}
