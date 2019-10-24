<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminReply extends Model
{
	protected $table = 'admin_replies';
	
    protected $fillable = [
        'content', 
    ];

    public function reply()
    {
    	return $this->belongsTo(Reply::class);
    }
}
