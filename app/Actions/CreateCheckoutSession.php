<?php

namespace App\Actions;

use App\Models\Product;
use App\Models\Order;

class CreateCheckoutSession
{
    public function __construct()
    {
        \Stripe\Stripe::setApiKey(config('cashier.secret'));
    }

    public static function handle($request)
    {
        $productId = $request->input('product_id');
        $product = Product::findOrFail($productId);

        $user = auth()->user();

        if (!$user->stripe_id) {
            $stripeCustomer = \Stripe\Customer::create([
                'email' => $user->email,
            ]);
            $user->stripe_id = $stripeCustomer->id;
            $user->save();
        }

        $sessionData = [
            'mode' => 'subscription',
            'payment_method_types' => ['card'],
            'line_items' => [
                [
                    'price' => $product->stripe_price_id,
                    'quantity' => 1,
                ],
            ],
            'customer' => $user->stripe_id,
            'customer_update' => [
                'address' => 'auto',
                'name' => 'auto',
            ],
            'tax_id_collection' => [
                'enabled' => true,
            ],
            'billing_address_collection' => 'required',
            'metadata' => [
                'user_id' => auth()->user()->id,
                'product_id' => $product->id,
            ],
            'success_url' => route('success', [], true)."?session_id={CHECKOUT_SESSION_ID}",
            'cancel_url' => route('cancel', [], true),
        ];

        if ($request->has('coupon_code')) {
            $sessionData['discounts'] = [['coupon' => $request->input('coupon_code')]];
        } else {
            $sessionData['allow_promotion_codes'] = true;
        }

        $session = \Stripe\Checkout\Session::create($sessionData);

        $order = new Order();
        $order->status = 'unpaid';
        $order->total_price = $product->price;
        $order->session_id = $session->id;
        $order->user_id = auth()->user()->id;
        $order->save();

        return redirect($session->url);
    }
}