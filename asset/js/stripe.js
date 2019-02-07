document.addEventListener('DOMContentLoaded', function () {

    var stripe_script = document.getElementById('stripe-app');
    var stripe_key = stripe_script.getAttribute('public-key');
    var locale_string = stripe_script.getAttribute('locale-key');
    console.log("DOM - Render, public Key: ", stripe_key);
    var stripe = Stripe(stripe_key); // stripe client Token INIT 

    // Inizializza Stripe Elementi
    var elements = stripe.elements({
        locale: locale_string // locale per multi language => Supported values are: ar, da, de, en, es, fi, fr, he, it, ja, no, nl, pl, sv, zh.
        // DOCUEMNTATION => https://stripe.com/docs/stripe-js/reference#locale
    }); 

    // Custom styling can be passed to options when creating an Element.
    var style = {
        base: {
          color: '#32325d',
          lineHeight: '18px',
          fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
          fontSmoothing: 'antialiased',
          fontSize: '16px',
          '::placeholder': {
            color: '#aab7c4'
          }
        },
        invalid: {
          color: '#fa755a',
          iconColor: '#fa755a'
        }
      };
  
    // Create an instance of the card Element.
    var card = elements.create('card', {style: style});
    // Add an instance of the card Element into the `card-element` <div>.
    card.mount('#card-element');

    // dopo aver creato l'elemento carta crea il listener sull'elemento
    card.addEventListener('change', function(event) {
        var displayError = document.getElementById('card-errors');
        if (event.error) {
        displayError.textContent = event.error.message;
        } else {
        displayError.textContent = '';
        }
    });


  // 2 - POI
  // Create a token or display an error when the form is submitted.
    var form = document.getElementById('payment-form');
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        // crea token di pagamento in base all'elemento CARD
        stripe.createToken(card).then(function(result) {
            if (result.error) {
                // Se sono presenti Errori
                var errorElement = document.getElementById('card-errors');
                errorElement.textContent = result.error.message;
            } else {
                // Send the token to your server.
                stripeTokenHandler(result.token); // callback
            }
        });
    });

    // 3 - Promise ON Resolved dopo la corretta generazione del TOKEN di pagamento
    function stripeTokenHandler(token) {
        // Insert the token ID into the form so it gets submitted to the server
        var form = document.getElementById('payment-form');

        // crea elemento tramite JS
        var hiddenInput = document.createElement('input');
        hiddenInput.setAttribute('type', 'hidden');
        hiddenInput.setAttribute('name', 'stripeToken');
        hiddenInput.setAttribute('value', token.id);
        form.appendChild(hiddenInput);
      
        // Submit the form - send to server
        form.submit();
        console.log("Payment Sended to server...");
      }

})