<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QueuedDemoChunk extends Model
{
  use HasFactory;
  protected $connection = 'mysql';

  public $timestamps = false;

  protected $table = 'queued_demo_chunks';

  protected $fillable = [
    'queued_demo_id',
    'chunk_index',
    'data'
  ];
}