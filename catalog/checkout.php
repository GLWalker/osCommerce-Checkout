<?php
/*
  $Id$ checkout.php
  G.L. Walker
  http://wsfive.com
  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com
  Copyright (c) 2010 osCommerce
  Released under the GNU General Public License
*/
  require('includes/application_top.php');
  require('includes/classes/http_client.php');

// if the customer is not logged on, redirect them to the login page
  if (!tep_session_is_registered('customer_id')) {
    $navigation->set_snapshot();
    tep_redirect(tep_href_link(FILENAME_LOGIN, '', 'SSL'));
  }

// if there is nothing in the customers cart, redirect them to the shopping cart page
  if ($cart->count_contents() < 1) {
    tep_redirect(tep_href_link(FILENAME_SHOPPING_CART));
  }
  
  // Stock Check
  if ( (STOCK_CHECK == 'true') && (STOCK_ALLOW_CHECKOUT != 'true') ) {
    $products = $cart->get_products();
    for ($i=0, $n=sizeof($products); $i<$n; $i++) {
      if (tep_check_stock($products[$i]['id'], $products[$i]['quantity'])) {
        tep_redirect(tep_href_link(FILENAME_SHOPPING_CART));
        break;
      }
    }
  }

  
// if no shipping destination address was selected, use the customers own address as default
  if (!tep_session_is_registered('sendto')) {
    tep_session_register('sendto');
    $sendto = $customer_default_address_id;
  } else {
// verify the selected shipping address
    if ( (is_array($sendto) && empty($sendto)) || is_numeric($sendto) ) {
      $check_address_query = tep_db_query("select count(*) as total from " . TABLE_ADDRESS_BOOK . " where customers_id = '" . (int)$customer_id . "' and address_book_id = '" . (int)$sendto . "'");
      $check_address = tep_db_fetch_array($check_address_query);

      if ($check_address['total'] != '1') {
        $sendto = $customer_default_address_id;
        if (tep_session_is_registered('shipping')) tep_session_unregister('shipping');
      }
    }
  }
// if no billing destination address was selected, use the customers own address as default
  if (!tep_session_is_registered('billto')) {
    tep_session_register('billto');
    $billto = $customer_default_address_id;
  } else {
// verify the selected billing address
    if ( (is_array($billto) && empty($billto)) || is_numeric($billto) ) {
      $check_address_query = tep_db_query("select count(*) as total from " . TABLE_ADDRESS_BOOK . " where customers_id = '" . (int)$customer_id . "' and address_book_id = '" . (int)$billto . "'");
      $check_address = tep_db_fetch_array($check_address_query);

      if ($check_address['total'] != '1') {
        $billto = $customer_default_address_id;
        if (tep_session_is_registered('payment')) tep_session_unregister('payment');
      }
    }
  }

// register a random ID in the session to check throughout the checkout procedure
// against alterations in the shopping cart contents
  if (!tep_session_is_registered('cartID')) {
    tep_session_register('cartID');
  } elseif (($cartID != $cart->cartID) && tep_session_is_registered('shipping')) {
    tep_session_unregister('shipping');
  }

  $cartID = $cart->cartID = $cart->generate_cart_id();


 
//  if (isset($_POST['payment'])) $payment = $_POST['payment'];
//  if (!tep_session_is_registered('payment')) tep_session_register('payment');

  require(DIR_WS_CLASSES . 'order.php');
  $order = new order;
  
  // load all enabled payment modules
  require(DIR_WS_CLASSES . 'payment.php');
  $payment_modules = new payment;
  
  require_once(DIR_WS_CLASSES . 'order.php');
  $order = new order;
	

  if($_GET['n']==1){
   
    if ( ( is_array($payment_modules->modules) && (sizeof($payment_modules->modules) > 1) && !is_object($$payment) ) || (is_object($$payment) && ($$payment->enabled == false)) ) {
	   
      tep_redirect(tep_href_link(FILENAME_CHECKOUT_SHIPPING, 'payment_error=' . urlencode(ERROR_NO_PAYMENT_MODULE_SELECTED), 'SSL'));

      tep_session_unregister('payment');
      $payment_modules->update_status();
    }
    if (is_array($payment_modules->modules)) {
      $payment_modules->pre_confirmation_check();
	//echo 'brrrr';
    }
  }
  
  while (list($key, $value) = each($_POST)) {
    $_SESSION[$key] = $value;
  }

  $total_weight = $cart->show_weight();
  $total_count = $cart->count_contents();

  // load all enabled shipping modules
  require(DIR_WS_CLASSES . 'shipping.php');
  $shipping_modules = new shipping;

  if ( defined('MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING') && (MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING == 'true') ) {
    $pass = false;

    switch (MODULE_ORDER_TOTAL_SHIPPING_DESTINATION) {
      case 'national':
        if ($order->delivery['country_id'] == STORE_COUNTRY) {
          $pass = true;
        }
        break;
      case 'international':
        if ($order->delivery['country_id'] != STORE_COUNTRY) {
          $pass = true;
        }
        break;
      case 'both':
        $pass = true;
        break;
    }

    $free_shipping = false;
    if ( ($pass == true) && ($order->info['total'] >= MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER) ) {
      $free_shipping = true;

      include(DIR_WS_LANGUAGES . $language . '/modules/order_total/ot_shipping.php');
    }
  } else {
    $free_shipping = false;
  }
	  
// process the selected shipping method
  if ( isset($HTTP_POST_VARS['action']) && ($HTTP_POST_VARS['action'] == 'process') && isset($HTTP_POST_VARS['formid']) && ($HTTP_POST_VARS['formid'] == $sessiontoken) ) {
    
	if (!tep_session_is_registered('comments')) tep_session_register('comments');
    if (tep_not_null($HTTP_POST_VARS['comments'])) {
      $comments = tep_db_prepare_input($HTTP_POST_VARS['comments']);
    }

    if (!tep_session_is_registered('shipping')) tep_session_register('shipping');

    if ( (tep_count_shipping_modules() > 0) || ($free_shipping == true) ) {
      if ( (isset($HTTP_POST_VARS['shipping'])) && (strpos($HTTP_POST_VARS['shipping'], '_')) ) {
        $shipping = $HTTP_POST_VARS['shipping'];

        list($module, $method) = explode('_', $shipping);
        if ( is_object($$module) || ($shipping == 'free_free') ) {
          if ($shipping == 'free_free') {
            $quote[0]['methods'][0]['title'] = FREE_SHIPPING_TITLE;
            $quote[0]['methods'][0]['cost'] = '0';
          } else {
            $quote = $shipping_modules->quote($method, $module);
          }
          if (isset($quote['error'])) {
            tep_session_unregister('shipping');
          } else {
            if ( (isset($quote[0]['methods'][0]['title'])) && (isset($quote[0]['methods'][0]['cost'])) ) {
              $shipping = array('id' => $shipping,
                                'title' => (($free_shipping == true) ?  $quote[0]['methods'][0]['title'] : $quote[0]['module'] . ' (' . $quote[0]['methods'][0]['title'] . ')'),
                                'cost' => $quote[0]['methods'][0]['cost']);

            }
          }
        } else {
          tep_session_unregister('shipping');
        }
      }
    } else {
      $shipping = false;
    }
  }

// get all available shipping quotes
  $quotes = $shipping_modules->quote();

// if no shipping method has been selected, automatically select the cheapest method.
// if the modules status was changed when none were available, to save on implementing
// a javascript force-selection method, also automatically select the cheapest shipping
// method if more than one module is now enabled
  if ( !tep_session_is_registered('shipping') || ( tep_session_is_registered('shipping') && ($shipping == false) && (tep_count_shipping_modules() > 1) ) ) $shipping = $shipping_modules->cheapest();
  
    
	
	//reinterate some sessions just to be sure
	
	if (!tep_session_is_registered('shipping')) tep_session_register('shipping');
    if (tep_not_null($_POST['shipping'])) {
      $shipping = $_POST['shipping'];
    }
	
	if (!tep_session_is_registered('payment')) tep_session_register('payment');
	if (tep_not_null($_POST['payment'])) {
		$payment = $_POST['payment'];
	}
	
	if (!tep_session_is_registered('comments')) tep_session_register('comments');
	if (tep_not_null($_POST['comments'])) {
		$payment = $_POST['comments'];
	}
  
 // if the order contains only virtual products, set some variables
  if ($order->content_type == 'virtual') {
    if (!tep_session_is_registered('shipping')) tep_session_register('shipping');
    $shipping = false;
    $sendto = false;
  }
	
  require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_CHECKOUT_SHIPPING);
  require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_CHECKOUT_PAYMENT);
	
	$breadcrumb->add(NAVBAR_TITLE, tep_href_link(FILENAME_CHECKOUT, '', 'SSL'));

	require(DIR_WS_INCLUDES . 'template_top.php');
?>
<script><!--
var selected;

function selectRowEffect(object, buttonSelect) {
  if (!selected) {
    if (document.getElementById) {
      selected = document.getElementById('defaultSelected');
    } else {
      selected = document.all['defaultSelected'];
    }
  }

  if (selected) selected.className = 'moduleRowShip';
  object.className = 'moduleRowSelectedShip';
  selected = object;

// one shipping button is not an array
  if (document.checkout_address.shipping[0]) {
    document.checkout_address.shipping[buttonSelect].checked=true;
  } else {
    document.checkout_address.shipping.checked=true;
  }
  
}

function rowOverEffect(object) {
  if (object.className == 'moduleRow') object.className = 'moduleRowOver';
}

function rowOutEffect(object) {
  if (object.className == 'moduleRowOver') object.className = 'moduleRow';
}
//--></script>
<?php echo $payment_modules->javascript_validation(); ?>


<div class="page-header">
  <h1><?php echo HEADING_TITLE; ?></h1>
</div>

<div class="contentContainer">


  <?php if ($banner = tep_banner_exists('dynamic', 'checkout-all-top-' . $language)) {  ?>
    <div class="row">
	  <?php echo tep_display_banner('static', $banner);  ?>
    </div>
  <?php } ?>
			

<?php
  if (isset($HTTP_GET_VARS['payment_error']) && is_object(${$HTTP_GET_VARS['payment_error']}) && ($error = ${$HTTP_GET_VARS['payment_error']}->get_error())) {
?>

  <div class="contentText">
    <?php echo '<strong>' . tep_output_string_protected($error['title']) . '</strong>'; ?>

    <p class="messageStackError"><?php echo tep_output_string_protected($error['error']); ?></p>
  </div>

<?php
  }
?>
</div>
	
<!-- billing address -->	
    <div class="row">
      
      <div class="col-md-6"> 
          <div class="alert alert-warning">
             <?php echo TEXT_SELECTED_BILLING_DESTINATION; ?>
             <?php echo tep_draw_button(IMAGE_BUTTON_CHANGE_ADDRESS, 'glyphicon-home', tep_href_link(FILENAME_CHECKOUT_PAYMENT_ADDRESS, '', 'SSL')); ?>
 		  </div>
      </div>
    	
	  <div class="col-md-6">
        <div id="billing-address" class="panel panel-primary">
	      <div class="panel-heading"><?php echo TITLE_BILLING_ADDRESS; ?></div>
	      <div class="panel-body">	
		    
	        <?php echo tep_address_label($customer_id, $billto, true, ' ', '<br />'); ?>
		
          </div>
        </div>
      </div>
    </div>
    <!-- billing address -->
    <hr>

<?php if  (tep_count_shipping_modules() > 0 && $shipping != false || $sendto == false) { ?>
    <!-- shipping address -->	
    <div class="row">
    
    	<div class="col-md-6">			
          <div class="alert alert-warning">
            <?php echo TEXT_CHOOSE_SHIPPING_DESTINATION; ?>
		    <?php echo tep_draw_button(IMAGE_BUTTON_CHANGE_ADDRESS, 'glyphicon-home', tep_href_link(FILENAME_CHECKOUT_SHIPPING_ADDRESS, '', 'SSL')); ?>
          </div>
        </div>
			
	    <div class="col-md-6">
          <div id="shipping-address" class="panel panel-info">
            <div class="panel-heading"><?php echo TITLE_SHIPPING_ADDRESS; ?></div>
		    <div class="panel-body">
             
              <?php echo tep_address_label($customer_id, $sendto, true, ' ', '<br />'); ?>
             
            </div>
          </div> 
		</div>
    </div>
	<!-- shipping address -->
    <hr />
<?php } ?>
			
<?php echo tep_draw_form('checkout_address', tep_href_link(FILENAME_CHECKOUT_CONFIRMATION, 'n=1', 'SSL'), 'post', 'class="form-horizontal" onsubmit="return check_form();"', true); ?>

    <div id="comments" class="row">       
      <div class="col-md-12">
	    <h4><?php echo TABLE_HEADING_COMMENTS; ?></h4> 
            <?php echo tep_draw_textarea_field('comments', 'soft', null, 4, $order->info['comments']); ?>
        <hr />
      </div>
    </div>

	
<?php if  (tep_count_shipping_modules() > 0 && $shipping != false || $sendto != false) { ?>
  <!-- shipping methods -->
  <div id="shipping-quotes" class="row">
    <div class="col-md-12">
      <div class="panel panel-default">
        <div class="panel-heading"><?php echo TABLE_HEADING_SHIPPING_METHOD; ?></div>
        
        
        <?php
        if ( (sizeof($quotes) > 1 && sizeof($quotes[0]) > 1) && ($free_shipping == false) ) {
	    ?>
          <div class="panel-body">
            <p><?php echo TEXT_CHOOSE_SHIPPING_METHOD ; ?></p>
          </div>
        <?php
        } elseif ($free_shipping == false) { ?>
          <div class="panel-body">
            <p><?php echo TEXT_ENTER_SHIPPING_INFORMATION; ?></p>
          </div>
        <?php
		}
		?>
       
		
        <table class="table table-striped table-condensed">
          <?php
          if ($free_shipping == true) {
		  ?>
	        <tr class="success">
              <th colspan="3"><?php echo FREE_SHIPPING_TITLE; ?>&nbsp;<?php echo $quotes[$i]['icon']; ?></th>
            </tr>

            <tr id="defaultSelected" class="moduleRowSelected" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="selectRowEffect(this, 0)">
              <td><?php echo sprintf(FREE_SHIPPING_DESCRIPTION, $currencies->format(MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER)) . tep_draw_hidden_field('shipping', 'free_free'); ?></td>
            </tr>
            
            <?php  
			} else {
              $radio_buttons = 0;
              for ($i=0, $n=sizeof($quotes); $i<$n; $i++) {
            ?>

             <tr class="info">
               <th colspan="3"><?php echo $quotes[$i]['module']; ?>&nbsp;<span class="pull-right" style="margin-right:15%;"><?php if (isset($quotes[$i]['icon']) && tep_not_null($quotes[$i]['icon'])) { echo $quotes[$i]['icon']; } ?></span></th>
             </tr>

           <?php if (isset($quotes[$i]['error'])) { ?>

              <tr class="error">
                <td colspan="3"><?php echo $quotes[$i]['error']; ?></td>
              </tr>

            <?php  } else {
              for ($j=0, $n2=sizeof($quotes[$i]['methods']); $j<$n2; $j++) {
                // set the radio button to be checked if it is the method chosen
                $checked = (($quotes[$i]['id'] . '_' . $quotes[$i]['methods'][$j]['id'] == $shipping['id']) ? true : false);

                 if ( ($checked == true) || ($n == 1 && $n2 == 1) ) {
                   echo '      <tr id="defaultSelected" class="moduleRowSelected" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="selectRowEffect(this, ' . $radio_buttons . ')">' . "\n";
                 } else {
                   echo '      <tr class="moduleRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="selectRowEffect(this, ' . $radio_buttons . ')">' . "\n";
                 }
				 ?>
                 
				 <td class="col-md-9"><?php echo $quotes[$i]['methods'][$j]['title']; ?></td>

<?php
            if ( ($n > 1) || ($n2 > 1) ) {
?>

              <td class="col-md-2"><?php echo $currencies->format(tep_add_tax($quotes[$i]['methods'][$j]['cost'], (isset($quotes[$i]['tax']) ? $quotes[$i]['tax'] : 0))); ?></td>
              <td  class="col-md-1 text-right"><?php echo tep_draw_radio_field('shipping', $quotes[$i]['id'] . '_' . $quotes[$i]['methods'][$j]['id'], 'required', $checked); ?></td>

<?php
            } else {
?>

               <td class="col-md-3 text-right" colspan="2"><?php echo $currencies->format(tep_add_tax($quotes[$i]['methods'][$j]['cost'], (isset($quotes[$i]['tax']) ? $quotes[$i]['tax'] : 0))) . tep_draw_hidden_field('shipping', $quotes[$i]['id'] . '_' . $quotes[$i]['methods'][$j]['id']); ?></td>

<?php
            }
?>

          </tr>				 
<?php
            $radio_buttons++;
          }
        }
      }
    }
?>
        </table>
      </div>
    </div>
  </div>
  <!-- shipping methods -->
   <hr />
<?php } ?>	
  <!-- payment methods -->
  <div id="payment-select" class="row">
    <div class="col-md-12">
      <div class="panel panel-default">
        <div class="panel-heading"><?php echo TABLE_HEADING_PAYMENT_METHOD; ?></div>
        <?php
         $selection = $payment_modules->selection();

         if (sizeof($selection) > 1) {
         ?>
           <div class="panel-body">
             <p><?php echo TEXT_SELECT_PAYMENT_METHOD ; ?></p>
           </div>
         <?php
         } elseif ($free_shipping == false) {
         ?>
           <div class="panel-body">
             <p><?php echo TEXT_ENTER_PAYMENT_INFORMATION; ?></p>
           </div>
         <?php
         }
		 ?>
         <table class="table table-striped table-condensed">
         
         <?php
         $radio_buttons = 0;
         for ($i=0, $n=sizeof($selection); $i<$n; $i++) {
         ?>
          

           <?php /*
           if ( ($selection[$i]['id'] == $payment) || ($n == 1) ) {
             echo '      <tr id="defaultSelected" class="moduleRowSelected" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="selectRowEffect(this, ' . $radio_buttons . ')">' . "\n";
           } else {
             echo '      <tr class="moduleRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="selectRowEffect(this, ' . $radio_buttons . ')">' . "\n";
           }
		   */
           ?>
           <tr>

            <th class="col-md-11"><label style="display:block;" for="<?php echo $selection[$i]['id']; ?>"><?php echo $selection[$i]['module']; ?></label></th>
            <td align="col-md-1">

              <?php
              if (sizeof($selection) > 1) {
                echo tep_draw_radio_field('payment', $selection[$i]['id'], ($selection[$i]['id'] == $payment),'required id="' . $selection[$i]['id'] .'"');
              } else {
                echo tep_draw_hidden_field('payment', $selection[$i]['id']);
              }
              ?>
            
            </td>
          </tr>

          <?php
          if (isset($selection[$i]['error'])) {
          ?>

            <tr>
              <td class="error" colspan="2"><?php echo $selection[$i]['error']; ?></td>
            </tr>

          <?php
          } elseif (isset($selection[$i]['fields']) && is_array($selection[$i]['fields'])) {
          ?>

            <tr>
              <td colspan="2"><table style="border:none;">

              <?php
              for ($j=0, $n2=sizeof($selection[$i]['fields']); $j<$n2; $j++) {
              ?>

                <tr>
                  <td style="border:none;"><?php echo $selection[$i]['fields'][$j]['title']; ?></td>
                  <td style="border:none;"><?php echo $selection[$i]['fields'][$j]['field']; ?></td>
                </tr>

              <?php
              }
              ?>

              </table></td>
            </tr>

          <?php
          }
          ?>

        <?php
        $radio_buttons++;
        }
        ?>
        </table>
      </div>
    </div>
  </div>
  <!-- payment methods -->
			
  
  <div class="buttonSet">
    <span class="buttonAction"><?php echo tep_draw_button(IMAGE_BUTTON_CONTINUE, 'glyphicon-chevron-right', null, 'primary'); ?></span>
  </div>
</form>

  <div class="row">
    <div class="stepwizard">
      <div class="stepwizard-row">
        <div class="stepwizard-step">
          <button type="button" class="btn btn-default btn-circle" disabled="disabled">1</button>
          <p><?php //echo CHECKOUT_BAR_DELIVERY; ?>Shopping Cart</p>
        </div>
        <div class="stepwizard-step">
          <button type="button" class="btn btn-primary btn-circle">2</button>
          <p><?php echo CHECKOUT_BAR_PAYMENT; ?></p>
        </div>
        <div class="stepwizard-step">
          <button type="button" class="btn btn-default btn-circle" disabled="disabled">3</button>
          <p><?php echo CHECKOUT_BAR_CONFIRMATION; ?></p>
        </div>
      </div>
    </div>
  </div>




		

		<?php if ($banner = tep_banner_exists('dynamic', 'checkout-all-bot-' . $language)) { ?>
			<section class="row">	
					<?php echo tep_display_banner('static', $banner);  ?>
			</section>
		<?php } ?>

<?php
  require(DIR_WS_INCLUDES . 'template_bottom.php');
  require(DIR_WS_INCLUDES . 'application_bottom.php');