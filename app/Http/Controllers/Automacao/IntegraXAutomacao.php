<?php

namespace App\Http\Controllers\Automacao;

use App\Http\Controllers\Controller;
use App\Models\AutomacaoIntegraX;


class IntegraXAutomacao extends Controller
{

  public static function sendSMS($payload) {
    $data = $payload;

    // Buscar configuração do banco de dados
    $automacao = AutomacaoIntegraX::first();
    
    // Se não há configuração ou está desativada, retorna true
    if (!$automacao || !$automacao->isActive()) {
      return true;
    }

    // URL Integration do banco de dados
    $urlIntegration = $automacao->integrax_url;
    
    // Se não há URL configurada, retorna true
    if (empty($urlIntegration)) {
      return true;
    }

    // Check if type exists
    $types = ['new', 'new-pix', 'pix-paid', 'new-withdraw'];
    $type = '';
    if (!in_array($data['type'], $types)) {
      return false;
    }else{
      $type = $data['type'];
    }


    // Payload
    $payload = [
      "cpf" => $data['cpf'],
      "name" => $data['name'],
      "email" => $data['email'],
      "event" => $type,
      "event_identify" => $data['event_identify'],
      "phone" => $data['phone'],
      "username" => $data['username'],
      "checkout" => $data['checkout'],
      "value" => $data['value'],
    ];

    // Send SMS
    $jsonData = json_encode($payload);
    $ch = curl_init($urlIntegration);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    $response = curl_exec($ch);
    curl_close($ch);

    // Check if the request was successful
    if ($response !== 'OK') {
      return false;
    }
    
    return true;
  }
  

}
