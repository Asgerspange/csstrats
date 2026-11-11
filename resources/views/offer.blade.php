@extends('layouts.offers')

@section('content')

<div class="container">
    <div class="logo"><img src="/images/logo.webp" alt=""></div>

    <div class="text-section">
        <div class="text-content">
            <h2>You have received an offer by {{ $offer['name'] }}</h2>
            <p>
                This offer gives you {{ $offer['coupon']['coupon']['percent_off'] }}% off 
                {{ $offer['coupon']['coupon']['duration'] }} for 
                @if ($offer['coupon']['coupon']['duration'] == 'repeating') 
                    {{ $offer['coupon']['coupon']['duration_in_months'] }} months for the following products
                @endif
            </p>
            <div class="product-list">
                @foreach ($offer['products'] as $index => $product)
                    <div class="product-item {{ $index === 0 ? 'selected' : '' }}" onclick="selectProduct({{ json_encode($product) }})">
                        <p>- {{ $product['name'] }}</p>
                    </div>
                @endforeach
            </div>
            <p>Use the code <b>{{ $offer['coupon']['code'] }}</b> at checkout to redeem this offer.</p>
        </div>
    </div>

    <div class="product-section">
        @if (!empty($offer['products']))
            @php $selectedProduct = $offer['products'][0]; @endphp
            <div class="product-card" id="product-card">
                <h3 id="product-name">{{ $selectedProduct['name'] }}</h3>
                <div class="product-features" id="product-features">
                    @foreach ($selectedProduct['features'] as $feature)
                        <p>{{ $feature }}</p>
                    @endforeach
                </div>
                <p id="product-description">{{ $selectedProduct['description'] ?? 'No description available' }}</p>
                @if (Auth::check())
                    <form action="{{ route('checkout') }}" method="POST">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $selectedProduct['id'] }}">
                        <input type="hidden" name="coupon_code" value="{{ $offer['coupon']['coupon']['id'] }}">
                        <button type="submit" class="buy-button">Buy Now</button>
                    </form>
                @else
                    <div class="mt-3">
                        <p>Sign in to buy this product.</p>
                        <a href="{{ route('login') }}" class="buy-button">Sign In</a>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>

@endsection

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: system-ui, sans-serif;
    }

    html, body {
        height: 100%;
        width: 100%;
        background-color: rgb(14, 24, 38);
        overflow-x: hidden;
    }

    .container {
        display: flex;
        flex-direction: column;
        width: 100%;
        position: relative;
    }

    .text-section {
        /* background-color: white; */
        /* background-color: #1a293d; */
        /* background: url('/images/bgtest.svg') no-repeat center center; */
        background: url('/images/ctdust.png');
        background-size: cover;
        background-position: -600px 0;
        color: white;
        width: 100%;
        height: 50vh;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 20px;
        text-align: center;
        position: relative;
    }

    .text-content {
        z-index: 2;
        background: rgba(0, 0, 0, 0.5);
        padding: 20px;
        border-radius: 10px;
    }

    .text-content h2 {
        font-size: 2rem;
        margin-bottom: 10px;
        text-align: center;
    }

    .product-section {
        background: url('/images/tnuke.png');
        background-size: cover;
        width: 100%;
        height: 50vh;
        display: flex;
        justify-content: center;
        align-items: center;
        position: relative;
    }

    .product-section::before, .text-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: inherit;
        filter: blur(10px);
        z-index: 1;
    }
    .svg-separator {
        position: absolute;
        left: 50%;
        top: 50%;
        width: 100%;
        height: 100%;
        transform: translateX(-50%) translateY(-50%);
        z-index: 1;
        pointer-events: none;
    }

    .svg-separator svg {
        display: block;
        width: 100%;
        overflow: hidden;
        height: 100%;
    }

    .product-list {
        display: flex;
        flex-direction: column;
        width: 300px;
        gap: 5px;
        margin-top: 8px;
        margin-bottom: 8px;
    }

    .product-item {
        cursor: pointer;
        padding: 8px 12px;
        transition: 0.3s;
        border-radius: 5px;
    }

    .product-item:hover,
    .product-item.selected {
        background-color: #58bc82;
        color: white;
    }

    .product-card {
        background: #0f141a !important;
        color: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0px 8px 16px rgba(0, 0, 0, 0.8);
        text-align: center;
        width: 300px;
        z-index: 2;
    }

    .buy-button {
        background: #28a745;
        color: white;
        padding: 10px 15px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        display: inline-block;
        margin-top: 10px;
    }

    .buy-button:hover {
        background: #218838;
    }

    .logo {
        position: absolute;
        top: 20px;
        left: 20px;
        z-index: 10;
        width: 125px;
        height: auto;
    }

    .logo img {
        width: 100%;
        height: 100%;
        filter: drop-shadow(4px 6px 4px rgba(0, 0, 0, 0.9));
    }

    /* Responsive Design */
    @media (min-width: 768px) {
        .container {
            flex-direction: row;
        }

        .text-section {
            width: 50%;
            height: 100vh;
            text-align: left;
            padding: 40px;
        }

        .product-section {
            width: 50%;
            height: 100vh;
        }
        
    }

    @media (max-width: 768px) {
        .svg-separator {
            width: 100%;
            height: 100%;
            transform: translateX(0) translateY(0);
        }
    }
</style>

<script>
 function selectProduct(product) {
    document.getElementById("product-name").textContent = product.name;
    document.getElementById("product-description").textContent = product.description ?? "No description available";
    document.getElementById("product-features").innerHTML = product.features.map(feature => `<p>${feature}</p>`).join('');

    document.querySelectorAll('.product-item').forEach(item => item.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
}
</script>
