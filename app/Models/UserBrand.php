<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserBrand extends Model
{
    use HasFactory;

    protected $table = 'v3_users_brands';
    protected $primaryKey = 'user_id';


//    public function user()
//    {
//        return $this->belongsTo(User::class);
//    }
//
//    public function brand()
//    {
//        return $this->belongsTo(Brand::class);
//    }
}
