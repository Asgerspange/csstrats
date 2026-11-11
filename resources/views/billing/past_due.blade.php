@extends('layouts.offers')

@section('content')

<div class="container">
    <div class="logo"><img src="/images/logo.webp" alt=""></div>
    <div class="text-section">
        <h2>Hello {{ $user['name'] }}, your latest invoice was not paid</h2>
        <p>Your account is currently past due. Please update your payment information to avoid any service interruptions.</p>
        <p>Pay and manage your subscription here: <a class="manage" onclick="goToStripe()">Stripe Dashboard</a></p>
        <br>
        <p>If you have any questions, please contact us at <a href="mailto:info@csstrats.dk">info@csstrats.dk</a></p>
        <small>If you fail to update your payment information within {{$due}}, your account will be suspended.</small>
    </div>
</div>
@endsection

<script>
    function goToStripe() {
        fetch('/api/stripe-portal', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        }).then(response => {
            return response.json();
        }).then(data => {
            window.location.href = data.url;
        })
    }
</script>

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: system-ui, sans-serif;
    }

    .manage {
        color: #007bff;
        text-decoration: underline;
    }

    html, body {
        height: 100%;
        width: 100%;
        background-color: #f4f4f4;
    }

    .container {
        display: flex;
        flex-direction: column;
        height: 100vh;
        width: 100vw;
    }

    .text-section {
        width: 100%;
        height: 50vh;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 20px;
        text-align: center;
    }

    .logo {
        position: absolute;
        top: 20px;
        left: 20px;
        width: 100px;
        height: auto;
    }

    .logo img {
        width: 100%;
        height: 100%;
        filter: drop-shadow(4px 6px 9px rgba(0, 0, 0, 0.7));
    }

    .image-section img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
</style>