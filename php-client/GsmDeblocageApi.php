<?php
/*
 * Client PHP (sans framework) pour l'API GSM Déblocage Serveur — format GSM Theme / Dhru Fusion.
 * Usage :
 *   require 'GsmDeblocageApi.php';
 *   $api = new GsmDeblocageApi('votre@email.com', getenv('GSMDS_API_KEY'));
 *   print_r($api->accountInfo());
 */
final class GsmDeblocageApi
{
    private string $url;

    public function __construct(private string $username, private string $apiKey, string $baseUrl = 'https://gsmdeblocageserveur.com/')
    {
        $p = parse_url($baseUrl);
        $this->url = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '') . '/public/api/index.php';
    }

    /** Appel brut : renvoie la réponse décodée (SUCCESS / ERROR) */
    public function call(string $action, ?array $parameters = null): array
    {
        $ch = curl_init($this->url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_POSTFIELDS => http_build_query([
                'username' => $this->username,
                'apiaccesskey' => $this->apiKey,
                'action' => $action,
                'requestformat' => 'JSON',
                'parameters' => $parameters ? base64_encode(json_encode($parameters)) : '',
            ]),
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) return ['ERROR' => [['MESSAGE' => 'Connexion impossible : ' . $err]]];
        return json_decode((string) $raw, true) ?: ['ERROR' => [['MESSAGE' => 'Réponse invalide']]];
    }

    /** Solde, devise, e-mail */
    public function accountInfo(): array
    {
        return $this->call('accountinfo');
    }

    /** Services (CREDIT, TIME, Requires.Custom, CUSTOM pour l'IMEI) */
    public function serviceList(): array
    {
        return $this->call('imeiservicelist');
    }

    /**
     * Commandes : [votreNumero => ['service' => SERVICEID, 'qnt' => 1, 'fields' => ['IMEI' => '...', 'username' => '...']]]
     * Réponse : SUCCESS[votreNumero]['referenceid'] (à garder) ou ERROR[votreNumero]['message']
     */
    public function placeOrders(array $orders): array
    {
        $params = [];
        foreach ($orders as $ref => $o) {
            $params[$ref] = ['ID' => $o['service'], 'QNT' => $o['qnt'] ?? 1, 'CUSTOMFIELD' => base64_encode(json_encode($o['fields'] ?? []))];
        }
        return $this->call('placebulkorder', $params);
    }

    /** Statuts : [votreNumero => referenceid]. STATUS 0/1 en cours, 3 rejetée (remboursée), 4 terminée (CODE = résultat) */
    public function orderStatus(array $references): array
    {
        $params = [];
        foreach ($references as $ref => $id) $params[$ref] = ['ID' => $id];
        return $this->call('getimeiorderbulk', $params);
    }
}
