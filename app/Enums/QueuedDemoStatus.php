<?php

namespace App\Enums;

enum QueuedDemoStatus: string
{
  case Uploading = 'uploading';
  case Pending = 'pending';
  case Processing = 'processing';
  case Failed = 'failed';
  case Completed = 'completed';
}
