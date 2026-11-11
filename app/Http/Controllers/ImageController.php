<?php

namespace App\Http\Controllers;

use App\Core\Traits\CachableTrait;
use App\Models\Image;
use Illuminate\Http\Request;

class ImageController extends Controller
{
    use CachableTrait;

    public function index(Request $request)
    {
        return Image::where('image_group', $request->get('group'))->get();
    }

    public function getDemoIcons()
    {
        $cacheKey = 'demo_icons_';
        $ttl = 360000000000; // 4 million days
        return self::rememberCache($cacheKey, $ttl, function () {
            $images = Image::where('image_group', 'demo_icon')->get();

            return $images;
        });
    }

    public function getColors()
    {
        $cacheKey = 'colors_';
        $ttl = 300000000000;

        return self::rememberCache($cacheKey, $ttl, function () {
            return Image::where('image_group', 'color')->get();
        });
    }

    public function store(Request $request)
    {
        if ($request->image_group == 'team') {
            Image::updateOrCreate([
                'image_group' => $request->image_group,
                'name' => $request->team_id
            ], [
                'base64' => $request->base64
            ]);
            return;
        }

        $user = app('user');
        Image::updateOrCreate([
            'image_group' => $request->image_group,
            'name' => $user->id
        ], [
            'base64' => $request->base64
        ]);
    }

    public function getImages()
    {
        return Image::where('image_group', 'image')->get();
    }
}
