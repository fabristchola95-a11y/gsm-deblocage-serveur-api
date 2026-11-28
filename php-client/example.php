<?php
/* Exemple : GSMDS_USER=votre@email.com GSMDS_API_KEY=VOTRE_CLE php php-client/example.php */
require __DIR__ . '/GsmDeblocageApi.php';

$api = new GsmDeblocageApi((string) getenv('GSMDS_USER'), (string) getenv('GSMDS_API_KEY'), getenv('GSMDS_URL') ?: 'https://gsmdeblocageserveur.com/');

$info = $api->accountInfo();
echo 'Solde : ' . ($info['SUCCESS'][0]['AccountInfo']['credit'] ?? json_encode($info)) . PHP_EOL;

$n = 0;
foreach (($api->serviceList()['SUCCESS'][0]['LIST'] ?? []) as $group) {
    foreach ($group['SERVICES'] as $s) {
        if ($n++ < 5) echo '#' . $s['SERVICEID'] . ' ' . $s['SERVICENAME'] . ' = ' . $s['CREDIT'] . PHP_EOL;
    }
}
echo $n . ' services disponibles' . PHP_EOL;

/* Commande (PAYANTE) : décommentez et remplacez SERVICEID et IMEI
$r = $api->placeOrders([1001 => ['service' => 123, 'fields' => ['IMEI' => '356789012345678']]]);
print_r($r);
print_r($api->orderStatus([1001 => $r['SUCCESS'][1001]['referenceid'] ?? 0]));
*/
