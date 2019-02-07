<?php
defined('BASEPATH') OR exit('No direct script access allowed');
// definisci costanti piano Abo (nickcname)
$conf_gold_plan = array(
	'name_product' => 'Premium Tuunes 2.99 USD / Month',
	'id' => 'gold_plan',
	'interval' => 'month', // day, week, month, and year
	'interval_count' => '1',
	'price' => '299',
	'trial' => '+3 days', // OR +3 minutes => Add 3 Days For Trial
	'num_ringtones' => '3'
);
define("GOLD_PLAN", json_encode($conf_gold_plan));

$conf_medium_plan = array(
	'name_product' => 'Premium Tuunes 9.99 USD / Year',
	'id' => 'medium_plan',
	'interval' => 'year', // day, week, month, and year
	'interval_count' => '1',
	'price' => '999',
	'trial' => '+7 days', // OR +3 minutes => Add 3 Days For Trial
	'num_ringtones' => '12'
);
define("MEDIUM_PLAN", json_encode($conf_medium_plan));

$conf_small_plan = array(
	'name_product' => 'Premium Tuunes 0.99 USD / Week',
	'id' => 'small_plan',
	'interval' => 'week', // day, week, month, and year
	'interval_count' => '1',
	'price' => '99',
	'trial' => NULL, // OR +3 minutes => Add 3 Days For Trial
	'num_ringtones' => '1'
);
define("SMALL_PLAN", json_encode($conf_small_plan));

require_once APPPATH.'third_party/Stripe_SDK/vendor/autoload.php'; // braintree lib 3.35.0 install via Composer

/*
 *  Stripe_lib by Marcello Fiore
 *	Stripe PHP SDK from 23.01.2019
 *  Codeigniter 3.1.10
 */

class Stripe_lib {

	protected $gateway;
	protected $public_key;

	function __construct() {
		$CI = &get_instance();
		$CI->config->load('stripe', TRUE); // load configuration file
		$stripe_config = $CI->config->item('stripe'); // Read File configuration with array

		// configutation Lib => application/config/stripe => SET YOUR DATA

		// Set your secret key: remember to change this to your live secret key in production
		// See your keys here: https://dashboard.stripe.com/account/apikeys
		$this->gateway =\Stripe\Stripe::setApiKey($stripe_config['secret_key']); //new Braintree\Gateway($config);
		\Stripe\Stripe::setApiVersion("2018-11-08"); // set version From API Stripe
		$this->public_key = $stripe_config['publishable_key'];

	}

	function getPublicKey(){
		return $this->public_key;
	}

	// CREAATE CHARGE NORMAL
	function charge($paymentTokenClient) {
		$charge = \Stripe\Charge::create([
			'amount' => 999,
			'currency' => 'usd',
			'source' => $paymentTokenClient, // token di pagamento da Stripe.js
			'receipt_email' => 'jenny.rosen@example.com',
			// 'description' => 'Example charge',
			//'metadata' => ['order_id' => 6735],
			// DOCUMENTAZIONE => https://stripe.com/docs/charges
		]);
		return $charge;
	}

	/*
	 * FOR SUBSCRIPTIONS...
	 */

	// 1 - CREATE A PRODUCT => https://stripe.com/docs/api/service_products/create
	// Si può creare un prodotto e collegarlo a diversi piani anche con diverse valute
	function createSubscriptionProduct($data = NULL) {
		if($data) {
			$product = \Stripe\Product::create([
				'name' => $data['product_name'], // Visible To User
				'type' => $data['product_type'],
				//'metadata' => ['order_id' => 6735],
				// FOR MORE DOC FIELD => https://stripe.com/docs/api/service_products/create
			]);
			return $product;
		} else {
			return "[ Stripe_lib => createSubscriptionProduct() ] => Please Configure Your Subscription Product => read https://stripe.com/docs/api/service_products/create";
		}
	}
	// 2 - Create Plan - For More Doc => https://stripe.com/docs/billing/subscriptions/products-and-plans
	// Working with Currency
	function createPlanWithProductID($data = NULL) {
		if($data) {
			$plan = \Stripe\Plan::create([
				'product' => $data['product_id'],
				'nickname' => $data['plan_nickname'], // A brief description of the plan, hidden from customers.
				'interval' => $data['plan_interval'], //'month', // day, week, month, and year
				'interval_count' => $data['plan_interval_count'], //'1', // 1
				'currency' => $data['currency'], //'usd',
				'amount' => $data['amount_cent'], // 999, // 9.99 => 999 /100
				//'metadata' => ['order_id' => 6735] // key => value
			]);
			return $plan;
		} else {
			return "[ Stripe_lib => createPlanWithProductID() ] => Please Configure Your Subscription Plan => read https://stripe.com/docs/billing/subscriptions/products-and-plans";
		}
	}
	// 3 - Create A Customer => more on documentation => https://stripe.com/docs/api/customers/object / https://stripe.com/docs/api/customers/update
	function createCustomerWithPaymentSource($data = NULL) {
		if($data) {
			$customer = \Stripe\Customer::create([
				'description' => $data['customer_description'], //'Customer for '.$name ?? "no-name",
				'email' => $data['email_customer'], //$emailUser ?? 'no-email',
				'source' => $data['source_payment_token'], //$sourcePaymentToken,
				//'metadata' => ['order_id' => 6735], // key > value
			]);
			return $customer;
		} else {
			return "[ Stripe_lib => createCustomerWithPaymentSource() ] => Configure Error on Create Customer => https://stripe.com/docs/api/customers/object / https://stripe.com/docs/api/customers/update ";
		}
	}
	// 4 - Create Subscription // https://stripe.com/docs/billing/quickstart => Step 3 da leggere i consigli che scrivono sotto
	function createSubscriptionWithPlanAndCustomer($data = NULL) {
		if($data) {
			$subscription = \Stripe\Subscription::create([
				'customer' => $data['customer_id'],
				'items' => [['plan' => $data['id_plan']]],
				'billing' => 'charge_automatically',
				'cancel_at_period_end' => 'false',
				// Questi due parametri in questa SDK non possono essere combinati! attualmente utilizzo l'ultima SDK
				'trial_end' => $data['timestump_trial_end'], // timestump di quando finisce il periodo trial di prova (esempio + 7 days)
				//'billing_cycle_anchor' => 'timestump' // imposta in modo fisso la data del rinnovo dell'abonamento => si Può usare con days abo? cercare per approfondimenti la documentazione
	
			]);
			return $subscription;
		} else {
			return "[ Stripe_lib => createSubscriptionWithPlanAndCustomer() ] => Configure Subscription Error => https://stripe.com/docs/billing/quickstart";
		}
		
	}
	// 5 - Cancel A Subscription // DOCUEMTNATION =>
	function cancelSubscriptionWithID($idSubscription = NULL) {
		// imposta come default alla funzione un id nullo, verifica quindi che l'ID sia sempre presente prima di inviare la richiesta
		if($idSubscription) {
			$subscription = \Stripe\Subscription::retrieve($idSubscription);
			$subscription->cancel(); // cancella subito la sottoscrizione
			/* OPPURE CANCELLA LA SOTTOSCRIZIONE ALLA FINE DEL PERIODO CORRENTE
			$subscription->cancel_at_period_end = true;
			$subscription->save();
			*/
			return $subscription;
		}
	}

	/**
	 * FOR WEBHOOK
	 */
	function verifyingSignaturesWebHook($payload, $sig_header) {
		$endpoint_secret = 'whsec_EqDeQ2U0vA46IgFdGSIFzLb3reAwnZs9';
		$event = null;
		try {
			$event = \Stripe\Webhook::constructEvent(
				$payload, $sig_header, $endpoint_secret
			);
		} catch(\UnexpectedValueException $e) {
			// Invalid payload
			http_response_code(400); // PHP 5.4 or greater
			exit();
		} catch(\Stripe\Error\SignatureVerification $e) {
			// Invalid signature
			http_response_code(400); // PHP 5.4 or greater
			exit();
		}
		return $event; // return event Decodificato
	}

	// HELPER PLAN
	function returnCreditFromPlan($idPan) {
		if($idPan) {
			$num_credit_to_set = 0;
			switch ($idPan) {
				case json_decode(GOLD_PLAN)->id:
					// INSERT 3 Ringtones
					$num_credit_to_set = json_decode(GOLD_PLAN)->num_ringtones;
					break;
				case json_decode(MEDIUM_PLAN)->id:
					// INSERT 12 Ringtones
					$num_credit_to_set = json_decode(MEDIUM_PLAN)->num_ringtones;
					break;
				case json_decode(SMALL_PLAN)->id:
					// INSERT 1 Ringtones
					$num_credit_to_set = json_decode(SMALL_PLAN)->num_ringtones;
					break;
				default:
					break;
			}
			return $num_credit_to_set;
		} else {
			return 0;
		}
		
	}

}