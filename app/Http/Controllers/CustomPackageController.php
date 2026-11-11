<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Stripe\StripeClient;

class CustomPackageController extends Controller
{
    protected $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('cashier.secret'));
    }

    /**
     * Display the custom package offer page.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function showOffer($id)
    {
        $product = Product::findOrFail($id);
        
        // Find monthly product variant
        $monthlyProduct = Product::where('prod_id', $product->prod_id)
            ->where('id', '!=', $product->id)
            ->first();
            
        $monthlyProductId = $monthlyProduct ? $monthlyProduct->id : null;
        
        return view('custom-package-checkout', [
            'product' => $product,
            'monthlyProductId' => $monthlyProductId
        ]);
    }
}