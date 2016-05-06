# osCommerce-Checkout
Combined shipping and payment page for osCommerce 2.3.4 - BS version

This is a combined shipping and payment setup that works out of the box with the bootstrap community build: https://github.com/gburton/osCommerce-2334-bootstrap

It is as close to default osCommerce of any other simular checkout page currently available.

To make it work on the standard 2.3.X versions the html needs to be changed to match - and the step wizard at bottom would have to be replaced or removed.

I pesonally do not like the way it looks out of the box - but all elements are there to be customized to fit a clients needs.

Changes are simple:

upload main file and language file

includes/filenames -

define checkout_shipping and define checkout_payment should both be set to checkout.php

If ever a need to code something new in place - you can still find the places to add in code withon the file as you would in the checkout_shipping or checkout_payment files.

I have used this file on several shops in the past - all the major debugging was done years ago.

I have kept it updated for the 2.3. series.
