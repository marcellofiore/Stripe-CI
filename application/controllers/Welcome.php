<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Welcome extends CI_Controller {

	function __construct(){
		parent::__construct();
		
        $this->load->helper('url'); // load helper URL
		$this->load->library('stripe_lib'); // Load Stripe Library
    }

	public function index() {
		// read client Token Stripe
		// IMPORRTANTE
		$data['stripe_client_token'] = $this->stripe_lib->getPublicKey();
		$this->load->view('welcome_message', $data);
	}

	// NORMALE CHARGE
	public function sendPay() {
		$data = $this->input->post();
		$result = $this->stripe_lib->charge($data['stripeToken']);
		print_r($result);
		echo "<br><br>";
		echo json_encode($result);
	}

	// FUNCTION SUBSCRIPTION
	public function createSubscription() {
		$data = $this->input->post();
		echo "<p>CREA SUBSCRIPTION CON FREE TRIAL</p>";

		echo "<p>----- CREATE PRODUCT -----</p>";
		$configProduct = array(
			'product_name' => 'Product Subscrioption NAME',
			'product_type' => 'service'
		);
		$result = $this->stripe_lib->createSubscriptionProduct($configProduct);
		$prod_id = $result->id;
		/*
		print_r($result);
		echo "<br><br>";
		echo json_encode($result);
		echo "<p><br>---- END PRODUCT -----<br></p>";
		*/

		// 2 - create plan
		echo "<p>----- CREATE PLAN -----</p>";
		$configPlan = array(
			'product_id' => $prod_id,
			'plan_nickname' => 'gold_plan',
			'plan_interval' => 'month', // day, week, month, and year
			'plan_interval_count' => '1', // 1 Month for example
			'amount_cent' => '299', // => 2.99 in centesimi
			'currency' => 'usd' // currency iso code
		);
		$result = $this->stripe_lib->createPlanWithProductID($configPlan);
		$idPlan = $result->id;
		// verificare che il piano sia attivo

		/*
		print_r($result);
		echo "<br><br>";
		echo json_encode($result);
		echo "<p><br>---- END PLAN -----<br></p>";
		*/
		

		// 3 - create customer
		echo "<p>----- CREATE CUSTOMER -----</p>";
		$emailUser = "mf@lauschmedia.de"; // opzionale
		$name = "Marcello";

		$configCustomer = array(
			'customer_description' => 'Customer Name: '.$name,
			'email_customer' => $emailUser,
			'source_payment_token' => $data['stripeToken']
		);
		$result = $this->stripe_lib->createCustomerWithPaymentSource($configCustomer); // associa al piano il pagamento che viene selezionato dall'utente
		$idCustomer = $result->id;
		/*
		print_r($result);
		echo "<br><br>";
		echo json_encode($result);
		echo "<p><br>----- END CUSTOMER-----<br></p>";
		*/

		// 4 - Associa al piano il customer con il pagamento selezionato e crea LA SUBSCRIPTION
		echo "<p>----- CREATE SUBSCRIPTION -----</p>";
		$trialEnd = $this->AddTime('+7 days'); // OR +3 minutes => Add 7 Days For Trial (al momento Ho impostato 1 ora per poter testare cosa succede quando il trial finisce)
		//$trialEnd = null; // niente freetrial

		$configSubscription = array(
			'customer_id' => $idCustomer,
			'id_plan' => $idPlan,
			'timestump_trial_end' => $trialEnd
		);
		$result = $this->stripe_lib->createSubscriptionWithPlanAndCustomer($configSubscription); // crea Piano Con trial
		//print_r($result);
		//echo "<br><br>";
		echo json_encode($result);
		echo "<p><br>END CREATAE SUBSCRIOPTION-----<br></p>";

		echo "<br>";
		echo "<p>DATA SUBSCRIPTION APPENA CREATA: </p>";
		echo "<br>SUB ID: ", $result->id;
		echo "<br>SUB START: ", $result->created; // timestump "start": 1548337802, (created)
		echo "<br>SUB STATUS: ", $result->status; // "status": "trialing", => altrimenti passa direttamente ad Active

		echo "<br>SUB TrialEnd: ", $result->trial_end; // timestump "trial_end": 1548942602, => se essite allora è uno start Trial
		echo "<br>SUB TrialStart: ", $result->trial_start; // timestump "trial_start": 1548337802 => se essite allora è uno start Trial

		/*
		"current_period_end": 1548953018,
		  "current_period_start": 1548348218,
		  */
		echo "<br>Current Period START => ", $result->current_period_start ;
		echo "<br>Current Period END => ", $result->current_period_end ;
		echo "<br>ID SUBSCRIPTION (nickname): ", $result->plan->nickname; // uso per separare il tipo di subscription!!! Ogni Subscription ha un Diverso Nickname
		// il Nickname deve essere salvato nel database insieme al codice Utente Per sapere dunque di quale prodotto si tratta nelle varie prossime renewl o invoice

	}

	// CANCELL SUBSCRIPTION
	public function cancel() {
		$idSubscription = "sub_EOxZruQVPixABU"; // SUBSCRIPTION ID DA CANCELLARE
		$result = $this->stripe_lib->cancelSubscriptionWithID($idSubscription);
		print_r($result);
	}

	// Helper => To timeStump + 7DAYS
	private function AddTime($time) {
		$newdate = strtotime($time, time());
		//$newdate = strtotime('+3 minutes', time());
		return $newdate;
	}

	/* HELPER CONVERT DATE */
	private function convertDate($date) {
		// CONVERTI FORMATO DATA
		$data_ricevuta = strtotime($date); // convert string to timestamp
		$data_convertita = date("Y-m-d H:i:s", $data_ricevuta); // convert timestamp to Server Format
		return $data_convertita;
	}
	// converti la data in dateShop
	private function createDateShop($date) {
		$data_ricevuta = strtotime($date); // convert string to timestamp
		$data_convertita = date("d/m/Y", $data_ricevuta); // convert timestamp to Server Format 31/08/2018
		return $data_convertita;
	}


}
