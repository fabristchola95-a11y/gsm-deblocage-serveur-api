<?php
/* ATTENTION : passe une VRAIE commande payée avec votre solde.
   Usage : GSM_USER=... GSM_KEY=... php examples/dhru_place_order.php <ID_SERVICE> <IMEI> ['{"champ":"valeur"}'] */
[$self, $id, $imei, $custom] = $argv + [null, null, null, ''];
if (!$id || !$imei) { fwrite(STDERR, "Usage : php examples/dhru_place_order.php <ID_SERVICE> <IMEI> ['{\"champ\":\"valeur\"}']\n"); exit(2); }
$url = 'https://gsmdeblocageserveur.com/api/index.php';
$post = function (string $action, string $xml) use ($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 90,
        CURLOPT_POSTFIELDS => http_build_query(['username' => getenv('GSM_USER'), 'apiaccesskey' => getenv('GSM_KEY'), 'action' => $action, 'requestformat' => 'JSON', 'parameters' => $xml])]);
    $r = json_decode((string) curl_exec($ch), true); curl_close($ch); return $r ?: [];
};
$xml = '<PARAMETERS><ID>' . (int) $id . '</ID><IMEI>' . htmlspecialchars($imei) . '</IMEI>'
     . ($custom !== '' ? '<CUSTOMFIELD>' . base64_encode($custom) . '</CUSTOMFIELD>' : '') . '</PARAMETERS>';
$r = $post('placeimeiorder', $xml);
if (!isset($r['SUCCESS'])) { echo 'Refusée : ' . ($r['ERROR'][0]['MESSAGE'] ?? 'erreur') . "\n"; exit(1); }
$ref = $r['SUCCESS'][0]['REFERENCEID'];
echo "Commande reçue : $ref\n";
for ($i = 0; $i < 30; $i++) {                      /* 30 minutes maximum, une requête par minute */
    sleep(60);
    $s = $post('getimeiorder', "<PARAMETERS><ID>$ref</ID></PARAMETERS>")['SUCCESS'][0] ?? [];
    echo date('H:i') . ' STATUS ' . ($s['STATUS'] ?? '?') . "\n";
    if (in_array($s['STATUS'] ?? '', ['3', '4'], true)) { echo "Résultat :\n" . ($s['CODE'] ?? '') . "\n"; break; }
}
