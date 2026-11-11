@extends('layouts.offers')

@section('content')
<div class="offer-page">
    <header class="offer-header">
        <div class="logo"><img src="/images/logo.webp" alt="Logo"></div>
        <h1>Custom Package Offer</h1>
        <p>We’ve tailored this package specifically for you. Explore its features and select your plan below.</p>
    </header>

    <div class="offer-body">
        <section class="features">
            <h2>Included Features</h2>
            <div class="features-grid">
                @foreach ($product->features as $feature)
                    <div class="feature-card">
                        <div class="icon">✓</div>
                        <p>{{ $feature }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="product">
            <div class="product-card">
                <h2 class="product-name">{{ $product->name }}</h2>
                <p class="product-description">{{ $product->description ?? 'No description available.' }}</p>

                <div class="pricing-options">
                    <div class="pricing-option yearly selected" onclick="selectPricing('yearly')">
                        <span>Yearly</span>
                        <strong>€{{ $product->price }}</strong>
                    </div>
                    <div class="pricing-option monthly" onclick="selectPricing('monthly')">
                        <span>Monthly</span>
                        <strong>€{{ $product->monthly_price }}</strong>
                    </div>
                </div>

                @if (Auth::check())
                    <form action="{{ route('checkout') }}" method="POST" id="checkout-form">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}" id="product-id-input">
                        <button type="submit" class="buy-button">Buy Now</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="buy-button">Sign In to Purchase</a>
                @endif
            </div>
        </section>
    </div>
</div>
@endsection

<style>
:root {
    --primary: #4f8ef7;
    --primary-light: #a0c4ff;
    --primary-dark: #1a5cff;
    --bg: #0f121a;
    --card-bg: #1c1f33;
    --card-hover: #25284d;
    --text-light: #e0e6ff;
    --text-muted: #9ca3af;
    --gradient: linear-gradient(135deg, #4f8ef7 0%, #1a5cff 100%);
    --shadow: 0 10px 25px rgba(0,0,0,0.3);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Inter', system-ui, sans-serif;
}

body, html {
    background: var(--bg);
    color: var(--text-light);
}

.offer-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 50px 20px;
    display: flex;
    flex-direction: column;
    gap: 60px;
}

.offer-header {
    text-align: center;
    max-width: 700px;
    margin: 0 auto;
}

.logo {
    width: 120px;
    margin: 0 auto 20px;
}

.logo img {
    width: 100%;
    height: auto;
}

.offer-header h1 {
    font-size: 2.5rem;
    color: var(--primary);
    margin-bottom: 10px;
}

.offer-header p {
    font-size: 1.1rem;
    color: var(--text-muted);
}

.offer-body {
    display: flex;
    flex-direction: column;
    gap: 60px;
}

.features h2 {
    text-align: center;
    font-size: 1.8rem;
    color: var(--primary);
    margin-bottom: 30px;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
}

.feature-card {
    background: var(--card-bg);
    padding: 20px;
    border-radius: 12px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    transition: background 0.3s, transform 0.3s;
    box-shadow: var(--shadow);
}

.feature-card:hover {
    background: var(--card-hover);
    transform: translateY(-5px);
}

.feature-card .icon {
    background: var(--gradient);
    color: white;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1rem;
    flex-shrink: 0;
}

.feature-card p {
    color: var(--text-light);
    font-size: 1rem;
}

.product {
    display: flex;
    justify-content: center;
}

.product-card {
    background: var(--card-bg);
    padding: 35px;
    border-radius: 15px;
    text-align: center;
    box-shadow: var(--shadow);
    max-width: 420px;
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 25px;
}

.product-name {
    font-size: 1.8rem;
    color: var(--primary);
}

.product-description {
    font-size: 1rem;
    color: var(--text-muted);
}

.pricing-options {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

.pricing-option {
    flex: 1;
    padding: 15px 10px;
    border-radius: 10px;
    background: var(--card-hover);
    cursor: pointer;
    transition: all 0.3s;
}

.pricing-option span {
    display: block;
    font-size: 0.9rem;
    color: var(--text-muted);
}

.pricing-option strong {
    font-size: 1.3rem;
    color: var(--primary);
}

.pricing-option.selected {
    background: var(--gradient);
    color: white;
}

.pricing-option:hover:not(.selected) {
    background: var(--primary-light);
}

.buy-button {
    background: var(--primary);
    color: #fff;
    font-size: 1rem;
    font-weight: 600;
    padding: 12px 25px;
    border-radius: 10px;
    border: none;
    cursor: pointer;
    transition: background 0.3s, transform 0.2s;
}

.buy-button:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
}
</style>

<script>
function selectPricing(type) {
    document.querySelectorAll('.pricing-option').forEach(opt => opt.classList.remove('selected'));
    document.querySelector(`.pricing-option.${type}`).classList.add('selected');
    document.getElementById('product-id-input').value =
        type === 'monthly' ? "{{ $monthlyProductId ?? $product->id }}" : "{{ $product->id }}";
}
</script>
