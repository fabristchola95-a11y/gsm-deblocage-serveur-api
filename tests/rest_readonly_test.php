<?php
/* Test lecture seule de l'API REST : balance, services, service. Usage : GSM_KEY=... php tests/rest_readonly_test.php */
$base = getenv('GSM_REST_URL') ?: 'https://gsmdeblocageserveur.com/api.php';
$key = getenv('GSM_KEY');
if (!$key) { fwrite(STDERR, "Définissez GSM_KEY.\n"); exit(2); }
function get(string $base, string $key, array $q): array {
    $ch = curl_init($base . '?' . http_build_query($q));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => ['X-API-Key: ' . $key]]);
    $raw = curl_exec($ch); curl_close($ch);
    return json_decode((string) $raw, true) ?: ['success' => false, 'message' => 'Réponse invalide'];
}
$fail = 0;
$check = function (string $name, bool $ok, string $detail) use (&$fail) { echo ($ok ? '[OK]   ' : '[ECHEC] ') . $name . ' — ' . $detail . "\n"; if (!$ok) $fail++; };
$b = get($base, $key, ['action' => 'balance']);
$check('balance', !empty($b['success']), ($b['data']['balance'] ?? '?') . ' ' . ($b['data']['currency'] ?? ''));
$s = get($base, $key, ['action' => 'services']);
$list = $s['data']['services'] ?? [];
$check('services', count($list) > 0, count($list) . ' services');
if ($list) {
    $one = get($base, $key, ['action' => 'service', 'id' => $list[0]['id']]);
    $check('service', !empty($one['success']), '#' . $list[0]['id'] . ' ' . $list[0]['name']);
}
exit($fail ? 1 : 0);
