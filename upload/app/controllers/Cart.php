<?php
/**
 * Shopping Cart
 *
 * @author dandelion <web.dandelion@gmail.com>
 * @package App_Cart
 */
class App_Cart extends Controller
{
    function indexAction(array $params)
    {
        $items = (array)json_decode(stripslashes(@$_COOKIE['cart']),true);
        
        $total = 0;
        $products = $this->model->product->getByIds(array_keys($items));
        foreach ($products as $product)
        {
        	$count = array_key_exists($product->id,$items) ? $items[$product->id] : 1;
        	$count = preg_match('@^[0-9]+$@i',$count) ? $count : 1;
        	$this->tpl->assignBlockVars('product', array(
                'ID'   => $product->id,
                'NAME' => $product->name,
                'PRICE' => $product->price,
                'COUNT' => $count,
            ));
            $total+= $count*$product->price;
        }
        /**
         * Доставка
         */
        $stuff = $this->model->stuff->get();
        if ($stuff->shipping && $total)
        {
            $this->tpl->assignBlockVars('shipping', array(
                'PRICE' => $stuff->shipping
            ));
            //if (!$stuff->free_shipping || $total<$stuff->free_shipping)
                //$total+= $stuff->shipping;
        }
        if ($stuff->free_shipping)
            $this->tpl->assignBlockVars('free_shipping', array(
                'PRICE' => $stuff->free_shipping
            ));
        /**
         * Итого
         */
        $this->tpl->assignVars(array(
            'PRICE_TOTAL'=>$total
        ));
        /**
         * See also
         */
        $also = explode('|',$stuff->cart_special);
        $exist = array_keys($items);
        foreach ($also as $k=>$id)
        {
            if (in_array($id,$exist))
               unset($also[$k]);
        }
        if ($also)
        {
            $entries = $this->model->product->getByIds($also, $total);
            foreach ($entries as $product)
            {
                $this->tpl->assignBlockVars('also', array(
                    'ID'   => $product->id,
                    'KEY'   => $product->key,
                    'NAME' => $product->name,
                    'BRIEF' => $product->brief,
                    'LABEL' => $product->label,
                    'PRICE' => $product->price,
                    'PRICE_OLD' => $product->price_old
                ));
                if ($product->picture)
                {
                    $this->tpl->assignBlockVars('also.picture', array(
                        'SRC'   => $product->picture
                    ));
                }
                if ($product->price)
                    $this->tpl->assignBlockVars('also.price');
                if ($product->price_old)
                    $this->tpl->assignBlockVars('also.price_old');
            }
            if (!$entries->isEmpty())
                $this->tpl->assignBlockVars('see_also');  
        }
    }
    
    function totalAction(array $params)
    {
        $items = (array)json_decode(stripslashes(@$_COOKIE['cart']),true);
        
        $total = 0;
        $products = $this->model->product->getByIds(array_keys($items));
        foreach ($products as $product)
        {
            $count = array_key_exists($product->id,$items) ? $items[$product->id] : 1;
            $count = preg_match('@^[0-9]+$@i',$count) ? $count : 1;
            $total+= $count*$product->price;
        }
        /**
         * Доставка
         */
        $stuff = $this->model->stuff->get();
        $shipping = $stuff->shipping;
        if ($shipping && $total && (!$stuff->free_shipping || $total<$stuff->free_shipping))
        {
            //$total+= $shipping;
        }
        die((string)$total);
    }
    
    function orderAction(array $params)
    {
        $items = (array)json_decode(stripslashes(@$_COOKIE['cart']),true);
        if (empty($_COOKIE['cart']) || empty($items))
            return $this->Url_redirectTo('cart');
        $products = $this->model->product->getByIds(array_keys($items));
        if ($products->isEmpty())
            die('products dont exist');
        
        $form = $this->load->form('cart/order');
        if ($form->isSubmit() && $form->isValid())
        {
            $data = new Entity(array_map('strip_tags',$form->getData()));
            /**
             * Сохранение данных
             */
            $id = $this->model->order->add(array(
                'cart' => stripslashes(@$_COOKIE['cart']),
                'name'  => $data->name,
                'phone' => $data->phone,
                'email' => $data->email,
                'address' => $data->address,
                'message' => $data->message,
                'step' => 1,
                'timestamp'  => time(),
                'ref' => (string)@$_COOKIE['ref'],
                'utm_source' => (string)@$_COOKIE['utm_source'],
                'utm_medium' => (string)@$_COOKIE['utm_medium'],
                'utm_term' => (string)@$_COOKIE['utm_term'],
                'utm_content' => (string)@$_COOKIE['utm_content'],
                'utm_campaign' => (string)@$_COOKIE['utm_campaign'],
                'referer' => (string)@$_COOKIE['referer'],
                'phrase' => (string)@$_COOKIE['phrase'],
            ));
            /**
             * Смета
             */
            $currency = $this->model->stuff->get()->currency;
            $total = 0;
            $orders = array();
	        foreach ($products as $product)
	        {
	            $count = array_key_exists($product->id,$items) ? $items[$product->id] : 1;
	            $count = preg_match('@^[0-9]+$@i',$count) ? $count : 1;
	            $total+= $count*$product->price;
	            $product->name = $product->name.($product->code ? " ($product->code)":'');
	            $orders[] = '<a href="http://'.$_SERVER['HTTP_HOST'].'/item/'.$product->key.'">'.$product->name.'</a> ('.$count.' шт) - '.($count*$product->price).' '.$currency;
	        }
            $stuff = $this->model->stuff->get();
            if ($stuff->shipping && $total && (!$stuff->free_shipping || $total<$stuff->free_shipping))
            {
                $orders[] = 'Доставка - '.($stuff->shipping).' '.$currency;
                $total+= $stuff->shipping;
            }
            /**
             * Отправка на мыло
             */
            $sent = false;
            if ($this->var->email)
            {
                require_once DIR_LIB.'/phpmailer/class.phpmailer.php';

                $mail = new PHPMailer();
                $mail->From     = 'no-reply@'.$_SERVER['HTTP_HOST'];
                $mail->FromName = $data->name ? $data->name : $_SERVER['HTTP_HOST'];
                $mail->AddReplyTo($data->email, $data->name);
                $mail->Host     = $_SERVER['HTTP_HOST'];
                if (YANDEX_SMTP_LOGIN && YANDEX_SMTP_PASS)
                {
                    $mail->From     = YANDEX_SMTP_LOGIN;//$data->email;
                    $mail->IsSMTP(); // enable SMTP
                    $mail->SMTPAuth = true;  // authentication enabled
                    $mail->Host = 'smtp.yandex.ru';
                    $mail->Port = 25; 
                    $mail->Username = YANDEX_SMTP_LOGIN;  
                    $mail->Password = YANDEX_SMTP_PASS;      
                }
                else
                {
                    $mail->Mailer   = "mail";
                }
                $mail->Body    = nl2br(
"Имя: $data->name
Телефон: $data->phone
E-mail: $data->email
Адрес: $data->address
Комментарии: $data->message
".(!empty($_COOKIE['ref']) ? "Реферал: {$_COOKIE['ref']}" : '')."

Заказ:
".join(PHP_EOL,$orders)."

ИТОГО: $total $currency

".(!empty($_COOKIE['referer']) ? "Источник: {$_COOKIE['referer']}" : '')."
".(!empty($_COOKIE['phrase']) ? "Фраза: {$_COOKIE['phrase']}" : '')."

".(!empty($_COOKIE['utm_source']) ? "Источник кампании (utm_source): {$_COOKIE['utm_source']}
Канал кампании (utm_medium): {$_COOKIE['utm_medium']}
Ключевое слово кампании (utm_term): {$_COOKIE['utm_term']}
Содержание кампании (utm_content): {$_COOKIE['utm_content']}
Название кампании (utm_campaign): {$_COOKIE['utm_campaign']}" : '')."");
                $mail->AltBody = strip_tags(str_replace("<br/>", "\n", $mail->Body));
                $mail->Subject = 'Заказ #'.$id;//$data->subject;
                $emails = array_map('trim',explode(',',$this->var->email));
                foreach ($emails as $email)
                   $mail->AddAddress($email);

                $sent = $mail->Send();

                if ($data->email)
                {
                    $stuff = $this->model->stuff->get();
                    $mail->Body    = nl2br(
"Здравствуйте!

Спасибо за заказ в нашем магазине! 

Вы указали следующие данные:

*********************************
Имя: $data->name
Телефон: $data->phone
E-mail: $data->email
Адрес: $data->address
Комментарии: $data->message

Заказ:
".join(PHP_EOL,$orders)."

ИТОГО: $total $currency
*********************************

Наш менеджер скоро свяжется с Вами.

--
С уважением,
администрация магазина
$stuff->site_name
$stuff->phone");
                    $mail->ClearAddresses();
                    $mail->From     = 'no-reply@'.$_SERVER['HTTP_HOST'];
                    $mail->FromName = $_SERVER['HTTP_HOST'];
                    $mail->AddAddress($data->email);
                                        
                    $mail->Send();
                }
            }
            /**
             * SMS
             */
            $stuff = $this->model->stuff->get();
            if ($stuff->sms_order_enable)
            {
                $phone1 = preg_replace('@[^0-9]+@i','',$data->phone);
                $phone2 = preg_replace('@[^0-9]+@i','',$stuff->sms_order_phone);
                $placeholders = array(
                    '{ID}' => $id,
                    '{NAME}' => $data->name,
                    '{PHONE}' => $data->phone,
                    '{TOTAL}' => $total.' '.$currency,
                );
                $text1 = str_replace(array_keys($placeholders),array_values($placeholders),$stuff->sms_order_client);
                $text2 = str_replace(array_keys($placeholders),array_values($placeholders),$stuff->sms_order_admin);
                
                if ($phone1 && $text1)
                    $this->model->sms->send($phone1,$text1);
                if ($phone2 && $text2)
                    $this->model->sms->send($phone2,$text2);
            }
            /**
             * Результат
             */
            setcookie('cart','',time() - 86400, COOKIE_PATH);
            if (isset($params[0]) && $params[0]=='pay')
            {
                $stuff = $this->model->stuff->get();
                if ($stuff->payment_system) 
                {
                    $function = "payBy".ucwords($stuff->payment_system);
                    if (is_callable(array($this,$function))) {
                        call_user_func(array($this,$function), $id, $total, $data, $stuff);
                    }
                }
                else die("Платежная система не настроена");
            }
            else
            {
                $this->model->order->edit(array(
                    'step' => 5,
                ),$id);
                $this->Url_redirectTo('cart/success/'.$id);
                die('ok');
            }
        }
        else $form->renderErrors($this->tpl);
    }
    
    function successAction(array $params)
    {
        $id = (int)@$params[0];
        if (!$id)
            die('id error');
        
            $order = $this->model->order->getById($id);
            $items = (array)json_decode($order->cart,true);
            $products = $this->model->product->getByIds(array_keys($items));
            $total = 0;
            $goods = array();
            foreach ($products as $product)
            {
                $count = array_key_exists($product->id,$items) ? $items[$product->id] : 1;
                $count = preg_match('@^[0-9]+$@i',$count) ? $count : 1;
                $total+= $count*$product->price;
                $goods[] = array(
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'quantity' => $count,
                );
            }
            
        $this->tpl->assignVars(array(
            'ID' => $id,
            'TOTAL' => $total,
            'GOODS' => json_encode($goods),
        ));
        
        $stuff = $this->model->stuff->get();
        if ($stuff->payment_system=='paypal')
        {
            $this->tpl->assignBlockVars('paypal',array(
                'BUSINESS' => $stuff->paypal_email
            ));
            foreach ($products as $i=>$product)
            {
                $count = array_key_exists($product->id,$items) ? $items[$product->id] : 1;
                $count = preg_match('@^[0-9]+$@i',$count) ? $count : 1;
                $total+= $count*$product->price;
                $this->tpl->assignBlockVars('paypal.item',array(
                    'NUM' => $i+1,
                    'NAME' => $product->name,
                    'PRICE' => $product->price,
                    'QUANTITY' => $count,
                ));
            }
        }
    }
    
    private function payByRobokassa($id,$total,$data,$stuff,$products)
    {
        if (empty($stuff->robokassa_id) || empty($stuff->robokassa_password1))
            die('Платежная система не настроена');
            
        $mrh_login = $stuff->robokassa_id;
        $mrh_pass1 = $stuff->robokassa_password1;
        $out_summ = $total;
        $inv_id = $id;
        $inv_desc = "Оплата заказа в магазине \"{$stuff->site_name}\"";
        $shp_item = 1;
        $in_curr = "PCR";
        $culture = "ru";
        $crc  = md5("$mrh_login:$out_summ:$inv_id:$mrh_pass1");
        $test_url = "http://test.robokassa.ru/Index.aspx";
        $final_url = "https://merchant.roboxchange.com/Index.aspx?".http_build_query(array(
            'MrchLogin' => $mrh_login,
            'OutSum' => $out_summ,
            'InvId' => $inv_id,
            'Desc' => $inv_desc,
            'SignatureValue' => $crc,
            //'IncCurrLabel' => $in_curr,
            'Culture' => $culture,
        ));
        die("<meta http-equiv=\"refresh\" content=\"0; url={$final_url}\"/>
        <p>Пожалуйста, подождите, идет перенаправление на сервис RoboKassa...</p>
        <p>Если ваш браузер не поддерживает автоматической переадресации, нажмите <a href=\"{$final_url}\">сюда</a></p>");
    }
    
    private function payByYandex($id,$total,$data,$stuff,$products)
    {
        if (empty($stuff->yandex_scid) || empty($stuff->yandex_shop_id))
            die('Платежная система не настроена');
            
        $final_url = "https://money.yandex.ru/eshop.xml?".http_build_query(array(
        		'scid' => $stuff->yandex_scid,
        		'ShopID' => $stuff->yandex_shop_id,
        		'Sum' => $total,
        		'CustomerNumber' => $id,
        		'CustName' => $data->name,
        		'CustAddr' => $data->address,
        		'CustEMail' => $data->email,
        		'OrderDetails' => $product->name,
        		'paymentType' => !empty($_POST['paymentType']) ? $_POST['paymentType'] : 'AC',
        ));
        die("<meta http-equiv=\"refresh\" content=\"0; url={$final_url}\"/>
        <p>Пожалуйста, подождите, идет перенаправление на сервис Яндекс.Деньги...</p>
        <p>Если ваш браузер не поддерживает автоматической переадресации, нажмите <a href=\"{$final_url}\">сюда</p>");
    }
    
    private function payByPaymaster($id,$total,$data,$stuff,$products)
    {
        if (empty($stuff->paymaster_id))
            die('Платежная система не настроена');
            
        $final_url = "https://paymaster.ru/Payment/Init?".http_build_query(array(
            'LMI_MERCHANT_ID' => $stuff->paymaster_id,
            'LMI_PAYMENT_AMOUNT' => $total,
            'LMI_CURRENCY' => 'RUB',
            'LMI_PAYMENT_NO' => $id,
            'LMI_PAYMENT_DESC_BASE64' => base64_encode("Заказ #$id"),
            'LMI_PAYER_PHONE_NUMBER' => preg_replace('@[^0-9]+@','',$data->phone),
            'LMI_PAYER_EMAIL' => $data->email,
        ));
        die("<meta http-equiv=\"refresh\" content=\"0; url={$final_url}\"/>
        <p>Пожалуйста, подождите, идет перенаправление на сервис Paymaster...</p>
        <p>Если ваш браузер не поддерживает автоматической переадресации, нажмите <a href=\"{$final_url}\">сюда</p>");
    }
	
	private function payByAssetpayments($id,$total,$data,$stuff,$products)
    {
        if (empty($stuff->merchant_id))
            die('Укажите ID мерчанта');
		if (empty($stuff->template_id))
            die('Укажите ID шаблона (по-умолчанию = 19)');
		if (empty($stuff->currency))
            die('Выберите валюту заказа');
            
        $ip = getenv('HTTP_CLIENT_IP')?:
			  getenv('HTTP_X_FORWARDED_FOR')?:
			  getenv('HTTP_X_FORWARDED')?:
			  getenv('HTTP_FORWARDED_FOR')?:
			  getenv('HTTP_FORWARDED')?:
			  getenv('REMOTE_ADDR');
			  
		$protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
		$hostname = $_SERVER['HTTP_HOST'];
		
		//****Required variables****//	
		$option['TemplateId'] = $stuff->template_id;
		$option['CustomMerchantInfo'] = 'BMshop';
		$option['MerchantInternalOrderId'] = $id;
		$option['StatusURL'] = $protocol . $hostname .'/assetpayments/result';	
		$option['ReturnURL'] = $protocol . $hostname .'/payment/success';
		$option['AssetPaymentsKey'] = $stuff->merchant_id;
		$option['Amount'] = $total;	
		$option['Currency'] = $stuff->ap_currency;
		$option['IpAddress'] = $ip;
		
		//****Phone number fix****//
		$phone = preg_replace('/[^\d]+/', '', $data->phone);
		
		//****Customer data and address****//
		$option['FirstName'] = $data->name;
        $option['Email'] = $data->email;
        $option['Phone'] = $phone;
        $option['Address'] = $data->address;
        $option['CountryISO'] = 'UKR';
		
		//var_dump ($option);
		
		$data = base64_encode( json_encode($option) );		
?>
	<html>
        <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8" />            
        </head>
        <body>
		
		<form method="POST" id="paymentform" name = "paymentform" action="https://assetpayments.us/checkout/pay" style="display:none;">
            <input type="hidden" name="data" value="<?php print $data?>" />
        </form>
        <br>
        <script type="text/javascript">document.getElementById('paymentform').submit();</script>
        </body>
    </html>
<?php	
    }
    
    private function payByPaypal($id,$total,$data,$stuff,$products)
    {
        if (empty($stuff->paypal_email))
            die('Платежная система не настроена');
        
        $this->Url_redirectTo('cart/success/'.$id);
    }
}