<?php

/*
 * Demo OAuth + CiviCRM-relaties ophalen voor SP-dashboard (VERSIE 2)
 * Kevin Levie, kevin@levity.nl, 30-10-2015
 */

require_once 'config.php';
require_once 'vendor/autoload.php';

$client = new OAuth2\Client(CLIENT_ID, CLIENT_SECRET);

if (!isset($_GET['action']) || $_GET['action'] != 'callback') {

  // Initialize OAuth

  $auth_url = $client->getAuthenticationUrl(ENDPOINT . 'authorize', REDIRECT_URL, [
    'scope' => 'sp civicrm',
    'state' => 'login',
  ]);
  header("Location: $auth_url");
  exit;

} else {

  // Callback when returning from OAuth provider

  if (!isset($_GET['code'])) {
    exit('Ongeldige aanroep.');
  }

  $response = $client->getAccessToken(ENDPOINT . 'token', 'authorization_code', [
    'code'         => $_GET['code'],
    'redirect_uri' => REDIRECT_URL,
  ]);

  if ($response && isset($response['result']['access_token'])) {

    // Drupal /me API call test

    $client->setAccessToken($response['result']['access_token']);

    // $res = $client->fetch(ENDPOINT . 'api/me');
    // $info = new \SimpleXMLElement($res['result']);

    $res = $client->fetch(ENDPOINT . 'api/me.json');
    $info = $res['result'];

    echo 'Succesvolle OAuth-authenticatie.<br />';

    if ($info['field_afdeling']) {
      echo 'Deze gebruiker is lid van de afdeling ' . $info['field_afdeling'] . ' (' . $info['field_afdeling_id'] . ').<br /><br />';
    }

    if ($info['roles'] && count($info['roles']) > 0) {
      echo 'Deze gebruiker heeft de volgende rollen:<br />';
      foreach ($info['roles'] as $role) {
        echo '- ' . $role['name'] . ' (' . $role['id'] . ')<br />';
      }
    }

    // CiviCRM API call test

    echo '<br />Met onze access token kunnen we nu ook wat CiviCRM API calls doen.<br />
            Dit zijn de eerste 20 contacten waartoe we toegang hebben:<br />';

    // Contact call test

    $contacts = $client->fetch(ENDPOINT . 'api/civiapi.json', [
      'key'            => CIVICRM_SITEKEY,
      'entity'         => 'Contact',
      'action'         => 'get',
      'options[limit]' => 20,
    ]);
    // var_dump($contacts);

    if ($contacts['code'] == 200 && !$contacts['result']['is_error']) {
      foreach ($contacts['result']['values'] as $lid) {
        echo '- ' . $lid['contact_id'] . ' ' . $lid['display_name'] . ' (' . $lid['street_address'] . ', ' . $lid['postal_code'] . ' ' . $lid['city'] . '; ' . $lid['phone'] . '; ' . $lid['email'] . '; ' . $lid['geo_code_1'] . ',' . $lid['geo_code_2'] . ')<br />'; //  - address-id: ' . $lid['address_id'] . '
      }
    } else {
      echo 'API-error: HTTP ' . $contacts['code'] . ' - Civi msg: ' . $contacts['result']['error_message'] . '<br /><br />';
    }

    echo '<br /><br /><a href="' . $_SERVER['PHP_SELF'] . '">Nog een keer</a>';

  } else {
    exit("Er is een fout opgetreden. (Response: " . print_r($response, TRUE) . ")");
  }
}
