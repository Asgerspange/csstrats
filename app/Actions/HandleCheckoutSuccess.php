<?php
namespace App\Actions;

use App\Mail\Usercreate;
use App\Models\MailListing;
use App\Models\Product;
use App\Models\User;
use App\Models\Order;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class HandleCheckoutSuccess
{
    public $products;

    public function __construct()
    {
        $this->products = Product::all();
        \Stripe\Stripe::setApiKey(config('cashier.secret'));
    }

    public static function handle($request)
    {
        $sessionId = $request->get('session_id');
        try {
            $session = \Stripe\Checkout\Session::retrieve($sessionId);
            $subscription = \Stripe\Subscription::retrieve($session->subscription);
            if (!$session) {
                throw new NotFoundHttpException;
            }
    
            $order = Order::where('session_id', $session->id)->first();

            if ($order) {
                $user = User::find($order->user_id);
            } else {
                $customer = \Stripe\Customer::retrieve($session->customer);
                $password = \Illuminate\Support\Str::random(10);
                $user = User::where('email', $customer->email)->first();
                if (!$user) {
                    $user = User::create([
                        'uuid' => (string) \Illuminate\Support\Str::uuid(),
                        'name' => $customer->name,
                        'email_verified_at' => now(),
                        'email' => $customer->email,
                        'password' => Hash::make($password),
                        'stripe_id' => $session->customer,
                        'auth_token' => bin2hex(openssl_random_pseudo_bytes(30)),
                    ]);
                }

                MailListing::create([
                    'user_id' => $user->id,
                    'news' => 1,
                ]);

                Mail::to($user->email)->send(new Usercreate($user, $password));

                $order = new Order();
                $order->status = 'unpaid';
                $order->total_price = $session->amount_total / 100;
                $order->session_id = $session->id;
                $order->user_id = $user->id;
                $order->save();
            }

            if ($order->status === 'unpaid') {
                $order->status = 'paid';
                $order->save();

                $productId = $session->metadata->product_id;
              	$product = Product::find($productId);
                if (!$product) {
                    $product = Product::where('stripe_price_id', $subscription->plan->id)->first();
                }

              	if (!$product) {
                  throw new NotFoundHttpException;
                }

                $user = User::findOrFail($order->user_id);
                $user->createOrGetStripeCustomer();

                $subscription = $user->subscriptions()->create([
                    'type' => 'default',
                    'stripe_id' => $subscription->id,
                    'stripe_status' => $subscription->status,
                    'stripe_price' => $product->stripe_price_id,
                    'quantity' => 1,
                    'trial_ends_at' => null,
                    'ends_at' => null,
                ]);

                $items = \Stripe\SubscriptionItem::all(['subscription' => $subscription->stripe_id]);
                $stripeId = $items->data[0]->id;

                $subscription->items()->create([
                    'subscription_id' => $subscription->id,
                    'stripe_id' => $stripeId,
                    'stripe_product' => $product->prod_id,
                    'stripe_price' => $product->stripe_price_id,
                    'quantity' => 1,
                ]);

                $user->save();
            }

            return redirect('/tactics');
        } catch (\Exception $e) {
            dd('Something went wrong, send a mail to bugs@csstrats.dk');
        }
    }
}
