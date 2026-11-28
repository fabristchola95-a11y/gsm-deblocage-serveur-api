<?php

/*
    Client API GSM Déblocage Serveur pour Laravel (format GSM Theme / Dhru Fusion).
    Adresse : https://gsmdeblocageserveur.com/public  ->  /public/api/index.php
    Support : WhatsApp +243 89 40 97 937 · support@gsmdeblocageserveur.com

    Configuration (.env) :
        GSMDS_API_URL=https://gsmdeblocageserveur.com/
        GSMDS_API_USERNAME=votre@email.com
        GSMDS_API_KEY=VOTRE_CLE_API
    Ne mettez jamais votre clé directement dans ce fichier ni dans un dépôt Git.
*/

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;

class GsmDeblocageApiController extends Controller
{
    /** Identifiants lus dans le fichier .env */
    private function credentials(): array
    {
        return [
            'url'          => env('GSMDS_API_URL', 'https://gsmdeblocageserveur.com/'),
            'username'     => env('GSMDS_API_USERNAME', ''),
            'apiaccesskey' => env('GSMDS_API_KEY', ''),
        ];
    }

    /** Appel commun : POST vers /public/api/index.php, paramètres en base64(JSON) */
    private function callApi(string $action, ?array $parameters = null): array
    {
        $api = $this->credentials();
        $parsed = parse_url($api['url']);
        $url = $parsed['scheme'] . '://' . $parsed['host'] . '/public/api/index.php';

        try {
            $response = Http::timeout(120)
                ->connectTimeout(15)
                ->retry(2, 2000)
                ->asForm()
                ->post($url, [
                    'username'      => $api['username'],
                    'apiaccesskey'  => $api['apiaccesskey'],
                    'action'        => $action,
                    'requestformat' => 'JSON',
                    'parameters'    => $parameters ? base64_encode(json_encode($parameters)) : '',
                ]);

            if ($response->failed()) {
                return ['ERROR' => [['MESSAGE' => 'HTTP ' . $response->status(), 'STATUS' => $response->status()]]];
            }

            return $response->json() ?? ['ERROR' => [['MESSAGE' => 'Réponse invalide']]];
        } catch (\Throwable $e) {
            return ['ERROR' => [['MESSAGE' => $e->getMessage()]]];
        }
    }

    /*
     * SOLDE DU COMPTE
     * Réponse :
     * {"SUCCESS":[{"message":"Your Accout Info","AccountInfo":{"credit":"50.00","creditraw":"50","mail":"vous@exemple.com","currency":"USD"}}],"apiversion":"6.1"}
     */
    public function getAccountInfo()
    {
        return $this->callApi('accountinfo');
    }

    /*
     * LISTE DES SERVICES (prix = CREDIT, délai = TIME)
     * - Services serveur : champs à envoyer dans "Requires.Custom" (fieldname, required)
     * - Services IMEI    : champ IMEI décrit dans "CUSTOM" (customname, customlen, maxlength)
     * Réponse :
     * {"SUCCESS":[{"MESSAGE":"IMEI Service List","LIST":{"UnlockTool":{"GROUPNAME":"UnlockTool","GROUPTYPE":"SERVER",
     *   "SERVICES":{"25":{"SERVICEID":25,"SERVICETYPE":"SERVER","SERVICENAME":"UnlockTool 1 Year","CREDIT":"30.00","TIME":"1-24h",
     *   "Requires.Custom":[{"fieldname":"username","fieldtype":"text","description":"Username","required":"on"}]}}}},
     *   "ACCOUNTINFO":{"credit":"50.00 USD","creditraw":50,"mail":"vous@exemple.com","currency":"USD"}}],"apiversion":"6.1"}
     */
    public function getServiceList()
    {
        return $this->callApi('imeiservicelist');
    }

    /*
     * PASSER DES COMMANDES (jusqu'à 100 par appel)
     * La clé du tableau (7, 8...) est VOTRE numéro de commande : il revient dans la réponse.
     * Le montant est débité de votre solde ; remboursement automatique si la commande est rejetée.
     * Réponse :
     * {"SUCCESS":{"7":{"status":"success","message":"Order received","referenceid":1001}},
     *  "ERROR":{"8":{"status":"error","message":"Champ obligatoire manquant : Username."}},"apiversion":"6.1"}
     * Gardez "referenceid" : il sert à suivre la commande.
     */
    public function placeOrder()
    {
        $orders = [
            [
                'id'            => 7,      // votre numéro de commande
                'api_server_id' => 25,     // SERVICEID chez GSM Déblocage Serveur
                'qnt'           => 1,
                'fields'        => [       // fieldname de "Requires.Custom"
                    'username' => 'identifiant_unlocktool',
                ],
            ],
            [
                'id'            => 8,
                'api_server_id' => 123,    // service IMEI
                'qnt'           => 1,
                'fields'        => [
                    'IMEI' => '356789012345678',
                ],
            ],
        ];

        $params = [];
        foreach ($orders as $order) {
            $params[$order['id']] = [
                'ID'          => $order['api_server_id'],
                'QNT'         => $order['qnt'],
                'CUSTOMFIELD' => base64_encode(json_encode($order['fields'] ?? [])),
            ];
        }

        return $this->callApi('placebulkorder', $params);
    }

    /*
     * STATUT ET RÉSULTAT (une fois par minute au maximum)
     * STATUS : 0 = en attente · 1 = en cours · 3 = rejetée (remboursée) · 4 = terminée (résultat dans CODE)
     * Réponse :
     * {"7":{"SUCCESS":[{"STATUS":"4","CODE":"Model: SM-A525F\nFRP: OFF","COMMENTS":""}]},"apiversion":"6.1"}
     */
    public function getOrder()
    {
        $orders = [
            ['id' => 7, 'referenceid' => 1001],
            ['id' => 8, 'referenceid' => 1002],
        ];

        $params = [];
        foreach ($orders as $order) {
            $params[$order['id']] = ['ID' => $order['referenceid']];
        }

        return $this->callApi('getimeiorderbulk', $params);
    }
}
