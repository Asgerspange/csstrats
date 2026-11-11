<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamDemoFolder extends Model
{
    public $timestamps = false;

    protected $table = 'team_demo_folders';
    protected $fillable = [
        'team_id',
        'demo_folder_id',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function demoFolder()
    {
        return $this->belongsTo(DemoFolder::class);
    }
}
