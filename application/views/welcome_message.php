<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title>Stripe SDK - TEST</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<!-- <link rel="stylesheet" type="text/css" media="screen" href="main.css" /> -->
	<style>
		/**
		* The CSS shown here will not be introduced in the Quickstart guide, but shows
		* how you can use CSS to style your Element's container.
		*/
		.StripeElement {
			background-color: white;
			height: 20px;
			padding: 10px 12px;
			border-radius: 4px;
			border: 1px solid transparent;
			box-shadow: 0 1px 3px 0 #e6ebf1;
			-webkit-transition: box-shadow 150ms ease;
			transition: box-shadow 150ms ease;
		}

		.StripeElement--focus {
			box-shadow: 0 1px 3px 0 #cfd7df;
		}

		.StripeElement--invalid {
			border-color: #fa755a;
		}

		.StripeElement--webkit-autofill {
			background-color: #fefde5 !important;
		}
		button.buttonPaymentStripe {
			border: none;
			border-radius: 4px;
			outline: none;
			text-decoration: none;
			color: #fff;
			background: #32325d;
			white-space: nowrap;
			display: inline-block;
			height: 40px;
			line-height: 40px;
			padding: 0 14px;
			box-shadow: 0 4px 6px rgba(50, 50, 93, .11), 0 1px 3px rgba(0, 0, 0, .08);
			border-radius: 4px;
			font-size: 15px;
			font-weight: 600;
			letter-spacing: 0.025em;
			text-decoration: none;
			-webkit-transition: all 150ms ease;
			transition: all 150ms ease;
			float: left;
			margin-left: 12px;
			margin-top: 28px;
			/*-webkit-appearance: button;*/
			/*-webkit-font-smoothing: antialiased;*/
			cursor: pointer;
			overflow: visible;
			text-transform: none;
			box-sizing: border-box;
			align-items: flex-start;
			text-align: center;
			text-indent: 0px;
			text-shadow: none;
			text-rendering: auto;
		}
		button.buttonPaymentStripe:hover {
			margin-top: 27px;
			box-shadow: 0 8px 6px rgba(50, 50, 93, .11), 0 4px 3px rgba(0, 0, 0, .08); /* due ombre */
			background-color: #43458B;
		}
		label.card-element {
			font-family: "Helvetica Neue", Helvetica, sans-serif;
			font-size: 16px;
			font-variant: normal;
			padding: 0;
			margin: 0;
			-webkit-font-smoothing: antialiased;
			font-weight: 400;
			font-size: 14px;
			display: block;
			margin-bottom: 8px;
			box-sizing: border-box;
			max-width: 100%;
			color: #6b7c93;
		}
		div#card-errors {
			color: red;
		}
	</style>
	<!-- frontEnd Stripe SDK -->
	<script src="https://js.stripe.com/v3/"></script>
</head>
<body>

	<!-- sendPay Normal Pay --> <!-- createSubscription -->
	<form action="<?php echo site_url('welcome/createSubscription') ?>" method="post" id="payment-form">
	<div class="form-row">

		<label for="card-element" class="card-element">Credit or debit card</label>

		<!-- componente credit Card -->
		<div id="card-element">
		<!-- A Stripe Element will be inserted here. -->
		</div>

		<!-- Used to display Element errors. -->
		<div id="card-errors" role="alert"></div>
	</div>

	<button class="buttonPaymentStripe">Submit Payment</button>
	</form>

	<script id="stripe-app" public-key="<?php echo $stripe_client_token ?>" locale-key="<?php echo trim(Locale::acceptFromHttp($_SERVER['HTTP_ACCEPT_LANGUAGE'])) ?>" src="<?php echo site_url('asset/js/stripe.js?v=0.1.2') ?>"></script>
</body>
</html>