<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use Stripe\PromotionCode;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    public function show($uuid)
    {
        \Stripe\Stripe::setApiKey(config('cashier.secret'));

        $offer = Offer::where('uuid', $uuid)->first();

        if (!$offer) {
            return redirect()->route('home');
        }
        $offer->products = $offer->product();
        $offer->coupon = PromotionCode::retrieve($offer->coupon)->toArray();

        if ($offer->end_date > now()) {
            return redirect()->route('home');
        }

        return view('offer', compact('offer'));
    }

    public function store(Request $request)
    {
        $request->merge(['uuid' => (string) \Illuminate\Support\Str::uuid()]);
        $request->merge(['ends_at' => \Carbon\Carbon::parse($request->ends_at)]);
        $offer = Offer::create($request->all());

        return redirect()->route('offer.show', $offer->uuid);
    }
}
