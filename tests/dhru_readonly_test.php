<?php
/* Test lecture seule de l'API Dhru : accountinfo, imeiservicelist, getimeiservicedetails. Usage : GSM_USER=... GSM_KEY=... php tests/dhru_readonly_test.php */
$url = getenv('GSM_API_URL') ?: 'https://gsmdeblocageserveur.com/api/index.php';
$user = getenv('GSM_USER'); $key = getenv('GSM_KEY');
if (!$user || !$key) { fwrite(STDERR, "Définissez GSM_USER et GSM_KEY.\n"); exit(2); }
function call(string $url, string $user, string $key, string $action, string $params = ''): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_POSTFIELDS => http_build_query(['username' => $user, 'apiaccesskey' => $key, 'action' => $action, 'requestformat' => 'JSON', 'parameters' => $params])]);
    $raw = curl_exec($ch); curl_close($ch);
    return json_decode((string) $raw, true) ?: ['ERROR' => [['MESSAGE' => 'Réponse invalide']]];
}
$fail = 0;
$check = function (string $name, bool $ok, string $detail) use (&$fail) { echo ($ok ? '[OK]   ' : '[ECHEC] ') . $name . ' — ' . $detail . "\n"; if (!$ok) $fail++; };

$a = call($url, $user, $key, 'accountinfo');
$info = $a['SUCCESS'][0]['AccoutInfo'] ?? null;
$check('accountinfo', is_array($info), $info ? 'solde ' . $info['credit'] . ' ' . $info['currency'] : json_encode($a));

$l = call($url, $user, $key, 'imeiservicelist');
$n = 0; $first = 0;
foreach (($l['SUCCESS'][0]['LIST'] ?? []) as $g) foreach ($g['SERVICES'] as $s) { $n++; $first = $first ?: (int) $s['SERVICEID']; }
$check('imeiservicelist', $n > 0, $n . ' services');

if ($first) {
    $d = call($url, $user, $key, 'getimeiservicedetails', "<PARAMETERS><ID>$first</ID></PARAMETERS>");
    $s = $d['SUCCESS'][0]['LIST'] ?? null;
    $check('getimeiservicedetails', is_array($s), $s ? '#' . $s['SERVICEID'] . ' ' . $s['SERVICENAME'] . ' = ' . $s['CREDIT'] : json_encode($d));
}

$bad = call($url, $user, 'cle-invalide', 'accountinfo');
$check('clé invalide refusée', isset($bad['ERROR']), $bad['ERROR'][0]['MESSAGE'] ?? 'non refusée');

exit($fail ? 1 : 0);
