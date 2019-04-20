<?php

class App_Assetpayments extends Controller
{
	function indexAction(array $params)
	{
		die();
	}

	function resultAction(array $params)
	{
	
		$json = json_decode(file_get_contents('php://input'), true);
			
		$stuff = $this->model->stuff->get();
		$key = $stuff->merchant_id;
		$secret = $stuff->secret_key;
		$transactionId = $json['Payment']['TransactionId'];
		$signature = $json['Payment']['Signature'];
		$order_id = $json['Order']['OrderId'];
		$status = $json['Payment']['StatusCode'];
		
		$requestSign =$key.':'.$transactionId.':'.strtoupper($secret);
		$sign = hash_hmac('md5',$requestSign,$secret);
		
		if ($status == 1 && $sign == $signature) {
		
			$this->model->order->edit(array(
				'status' => "Заказ оплачен! (AssetPayments №" .$transactionId.')'
			),$order_id);
		
		}
		
		if ($status == 2 && $sign == $signature) {
		
			$this->model->order->edit(array(
				'status' => "Ошибка оплаты! (AssetPayments №" .$transactionId.')'
			),$order_id);
		
		}
		
	}
}
