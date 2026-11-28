<?php

/*
|--------------------------------------------------------------------------
| STANDARD API COMPATIBLE — GSM Déblocage Serveur (format GSM Theme / Dhru Fusion)
|--------------------------------------------------------------------------
| Pour qui ? Vous créez un site d'outils (licences, crédits, IMEI...) et voulez que
| GSM Déblocage Serveur — ou tout panneau Dhru Fusion / GSM Theme — se connecte à
| votre site pour importer vos services, passer les commandes et récupérer les résultats.
|
| Ce contrôleur Laravel décrit EXACTEMENT les requêtes reçues et les réponses attendues.
| Remplacez les méthodes marquées « À BRANCHER » par votre propre base de données.
|
| Point d'accès : POST https://votre-site.com/public/api/index.php
|   (route : Route::post('/public/api/index.php', [PublicApiController::class, 'publicApi']);
|    à exclure de la vérification CSRF)
|
| Champs reçus (formulaire) :
|   username       e-mail du revendeur
|   apiaccesskey   clé API du revendeur
|   action         accountinfo | imeiservicelist | getimeiorder | getimeiorderbulk | placeimeiorder | placebulkorder
|   requestformat  JSON
|   parameters     XML <PARAMETERS>…</PARAMETERS> (actions simples) ou base64(JSON) (actions groupées)
|
| Statuts de commande : 0 = en attente · 1 = en cours · 3 = rejetée (remboursée) · 4 = terminée
| Toutes les réponses contiennent "apiversion". Réponse HTTP 200 même en cas d'erreur.
|--------------------------------------------------------------------------
*/

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicApiController extends Controller
{
    private const API_VERSION = '6.1';

    public function publicApi(Request $request): JsonResponse
    {
        $customer = $this->authenticate((string) $request->input('username'), (string) $request->input('apiaccesskey'));
        if (!$customer) {
            return $this->error('Authentication Failed');
        }

        return match (strtolower((string) $request->input('action'))) {
            'accountinfo'      => $this->accountInfo($customer),
            'imeiservicelist'  => $this->imeiServiceList($customer),
            'getimeiorder'     => $this->getImeiOrder($request, $customer),
            'getimeiorderbulk' => $this->getImeiOrderBulk($request, $customer),
            'placeimeiorder'   => $this->placeImeiOrder($request, $customer),
            'placebulkorder'   => $this->placeBulkOrder($request, $customer),
            default            => $this->error('Invalid Action'),
        };
    }

    /* ---------------------------------------------------------------
     * accountinfo
     * Réponse :
     * {"SUCCESS":[{"MESSAGE":"Your Account Info","AccountInfo":{"credit":"50.00 USD","creditraw":50,"mail":"client@mail.com","currency":"USD"}}],"apiversion":"6.1"}
     * ------------------------------------------------------------- */
    private function accountInfo(array $customer): JsonResponse
    {
        $info = [
            'credit'    => number_format($customer['balance'], 2, '.', '') . ' ' . $customer['currency'],
            'creditraw' => round($customer['balance'], 2),
            'mail'      => $customer['email'],
            'currency'  => $customer['currency'],
        ];

        return $this->json(['SUCCESS' => [['MESSAGE' => 'Your Account Info', 'AccountInfo' => $info]]]);
    }

    /* ---------------------------------------------------------------
     * imeiservicelist : services groupés, prix pour CE revendeur
     * - Service SERVER : champs à saisir dans "Requires.Custom" (fieldname = clé envoyée dans CUSTOMFIELD)
     * - Service IMEI   : champ principal décrit dans "CUSTOM" (customname, customlen, maxlength)
     * Réponse :
     * {"SUCCESS":[{"MESSAGE":"Service List","LIST":{
     *    "UnlockTool":{"GROUPNAME":"UnlockTool","GROUPTYPE":"SERVER","SERVICES":{
     *       "25":{"SERVICEID":25,"SERVICETYPE":"SERVER","QNT":1,"MINQNT":"","MAXQNT":"","SERVICENAME":"UnlockTool 1 Year",
     *             "CREDIT":30,"TIME":"1-24h","INFO":"",
     *             "Requires.Custom":[{"type":"serviceimei","fieldname":"Username","fieldtype":"text","description":"","fieldoptions":"","required":"on"}]}}},
     *    "Samsung":{"GROUPNAME":"Samsung","GROUPTYPE":"IMEI","SERVICES":{
     *       "123":{"SERVICEID":123,"SERVICETYPE":"IMEI","QNT":0,"SERVICENAME":"Samsung FRP","CREDIT":3.9,"TIME":"Instant","INFO":"",
     *              "CUSTOM":{"allow":"1","bulk":"0","customname":"IMEI","custominfo":"15 chiffres","customlen":"15","maxlength":"15","regex":"","isalpha":"0"}}}}},
     *  "ACCOUNTINFO":{"credit":"50.00 USD","creditraw":50,"mail":"client@mail.com","currency":"USD"}}],"apiversion":"6.1"}
     * ------------------------------------------------------------- */
    private function imeiServiceList(array $customer): JsonResponse
    {
        $list = [];
        foreach ($this->servicesFor($customer) as $s) {
            $item = [
                'SERVICEID'   => $s['id'],
                'SERVICETYPE' => $s['type'],                 // IMEI | SERVER
                'QNT'         => $s['type'] === 'SERVER' ? 1 : 0,
                'MINQNT'      => '',
                'MAXQNT'      => '',
                'SERVICENAME' => $s['name'],
                'CREDIT'      => $s['price'],
                'TIME'        => $s['time'],
                'INFO'        => $s['info'],
            ];
            if ($s['type'] === 'IMEI') {
                $item['CUSTOM'] = ['allow' => '1', 'bulk' => '0', 'customname' => $s['imei_label'] ?? 'IMEI', 'custominfo' => '',
                    'customlen' => (string) ($s['imei_min'] ?? 15), 'maxlength' => (string) ($s['imei_max'] ?? 15), 'regex' => '', 'isalpha' => '0'];
            }
            $custom = [];
            foreach ($s['fields'] as $f) {
                $custom[] = ['type' => 'serviceimei', 'fieldname' => $f['name'], 'fieldtype' => $f['type'] ?? 'text',
                    'description' => $f['help'] ?? '', 'fieldoptions' => $f['options'] ?? '', 'required' => !empty($f['required']) ? 'on' : ''];
            }
            if ($custom) {
                $item['Requires.Custom'] = $custom;
            }
            $list[$s['group']] ??= ['GROUPNAME' => $s['group'], 'GROUPTYPE' => $s['type'], 'SERVICES' => []];
            $list[$s['group']]['SERVICES'][(string) $s['id']] = $item;
        }

        return $this->json(['SUCCESS' => [['MESSAGE' => 'Service List', 'LIST' => $list, 'ACCOUNTINFO' => $this->accountArray($customer)]]]);
    }

    /* ---------------------------------------------------------------
     * placeimeiorder : parameters = <PARAMETERS><ID>123</ID><IMEI>356…</IMEI><QNT>1</QNT><CUSTOMFIELD>base64(JSON)</CUSTOMFIELD></PARAMETERS>
     * Réponse : {"SUCCESS":[{"MESSAGE":"Order received","REFERENCEID":1001}],"apiversion":"6.1"}
     * Erreur  : {"ERROR":[{"MESSAGE":"Not enough balance"}],"apiversion":"6.1"}
     * ------------------------------------------------------------- */
    private function placeImeiOrder(Request $request, array $customer): JsonResponse
    {
        $p = @simplexml_load_string((string) $request->input('parameters'));
        if (!$p || !isset($p->ID)) {
            return $this->error('Parameter or Service <ID> missing.');
        }
        $fields = $this->decodeCustomField((string) ($p->CUSTOMFIELD ?? ''));
        if (!empty($p->IMEI)) {
            $fields['IMEI'] = (string) $p->IMEI;
        }
        $r = $this->createOrder($customer, (int) $p->ID, max(1, (int) ($p->QNT ?? 1)), $fields);

        return isset($r['id'])
            ? $this->json(['SUCCESS' => [['MESSAGE' => 'Order received', 'REFERENCEID' => $r['id']]]])
            : $this->error($r['error']);
    }

    /* ---------------------------------------------------------------
     * placebulkorder : parameters = base64({"7":{"ID":25,"QNT":1,"CUSTOMFIELD":"base64(JSON)"},"8":{...}})
     * La clé (7, 8) est le numéro de commande du client : elle revient dans la réponse.
     * Réponse (succès ET erreurs dans SUCCESS) :
     * {"SUCCESS":{"7":{"status":"success","message":"Order received","referenceid":1001},
     *             "8":{"status":"error","message":"Not enough balance"}},"apiversion":"6.1"}
     * ------------------------------------------------------------- */
    private function placeBulkOrder(Request $request, array $customer): JsonResponse
    {
        $orders = $this->decodeBulk((string) $request->input('parameters'));
        if ($orders === null) {
            return $this->error('Parameters must be base64 encoded JSON.');
        }
        $out = [];
        foreach ($orders as $ref => $o) {
            if (empty($o['ID'])) {
                $out[$ref] = ['status' => 'error', 'message' => 'Missing targeted service id.'];
                continue;
            }
            $r = $this->createOrder($customer, (int) $o['ID'], max(1, (int) ($o['QNT'] ?? 1)), $this->decodeCustomField((string) ($o['CUSTOMFIELD'] ?? '')));
            $out[$ref] = isset($r['id'])
                ? ['status' => 'success', 'message' => 'Order received', 'referenceid' => $r['id']]
                : ['status' => 'error', 'message' => $r['error']];
        }

        return $this->json(['SUCCESS' => $out]);
    }

    /* ---------------------------------------------------------------
     * getimeiorder : parameters = <PARAMETERS><ID>1001</ID></PARAMETERS>
     * Réponse : {"SUCCESS":[{"STATUS":4,"CODE":"résultat du service"}],"apiversion":"6.1"}
     * ------------------------------------------------------------- */
    private function getImeiOrder(Request $request, array $customer): JsonResponse
    {
        $p = @simplexml_load_string((string) $request->input('parameters'));
        if (!$p || !isset($p->ID)) {
            return $this->error('Parameter required.');
        }
        $o = $this->findOrder($customer, (int) $p->ID);
        if (!$o) {
            return $this->error('Order ID not found!');
        }

        return $this->json(['SUCCESS' => [['STATUS' => $o['status'], 'CODE' => $o['status'] >= 3 ? $o['result'] : '']]]);
    }

    /* ---------------------------------------------------------------
     * getimeiorderbulk : parameters = base64({"7":{"ID":1001},"8":{"ID":1002}})
     * Réponse : {"7":{"SUCCESS":[{"STATUS":4,"CODE":"…"}]},"8":{"SUCCESS":[{"STATUS":1,"CODE":""}]},"apiversion":"6.1"}
     * Commande inconnue : STATUS 0 (jamais d'erreur dans le suivi groupé).
     * ------------------------------------------------------------- */
    private function getImeiOrderBulk(Request $request, array $customer): JsonResponse
    {
        $items = $this->decodeBulk((string) $request->input('parameters'));
        if ($items === null) {
            return $this->error('Parameters must be base64 encoded JSON.');
        }
        $out = [];
        foreach ($items as $ref => $it) {
            $o = $this->findOrder($customer, (int) ($it['ID'] ?? 0));
            $status = $o['status'] ?? 0;
            $out[$ref]['SUCCESS'][] = ['STATUS' => $status, 'CODE' => ($o && $status >= 3) ? $o['result'] : ''];
        }

        return $this->json($out);
    }

    /* =============================== OUTILS =============================== */

    private function json(array $data): JsonResponse
    {
        $data['apiversion'] = self::API_VERSION;

        return response()->json($data, 200)->header('Cache-Control', 'no-store');
    }

    private function error(string $message): JsonResponse
    {
        return $this->json(['ERROR' => [['MESSAGE' => $message]]]);
    }

    private function accountArray(array $c): array
    {
        return ['credit' => number_format($c['balance'], 2, '.', '') . ' ' . $c['currency'], 'creditraw' => round($c['balance'], 2), 'mail' => $c['email'], 'currency' => $c['currency']];
    }

    /** base64(JSON) -> tableau, ou null si invalide */
    private function decodeBulk(string $encoded): ?array
    {
        $json = base64_decode($encoded, true);
        $data = $json === false ? null : json_decode($json, true);

        return is_array($data) ? $data : null;
    }

    /** CUSTOMFIELD = base64(JSON) des champs (clés = fieldname de Requires.Custom, ou IMEI) */
    private function decodeCustomField(string $encoded): array
    {
        return $encoded === '' ? [] : ($this->decodeBulk($encoded) ?? []);
    }

    /* ============================ À BRANCHER ============================
     * Reliez ces 4 méthodes à votre base de données.
     */

    /** Revendeur actif dont l'e-mail et la clé correspondent : ['id','email','balance','currency'] ou null */
    private function authenticate(string $username, string $apiKey): ?array
    {
        return null; // À BRANCHER
    }

    /** Services en vente : [['id','type'=>'IMEI|SERVER','group','name','price','time','info','fields'=>[['name','required']]], ...] */
    private function servicesFor(array $customer): array
    {
        return []; // À BRANCHER
    }

    /**
     * Crée la commande : vérifie le service, les champs obligatoires et le solde, débite le solde.
     * Retourne ['id' => numéro de commande] ou ['error' => 'message'].
     */
    private function createOrder(array $customer, int $serviceId, int $qnt, array $fields): array
    {
        return ['error' => 'Not implemented']; // À BRANCHER
    }

    /** Commande du revendeur : ['status' => 0|1|3|4, 'result' => texte] ou null */
    private function findOrder(array $customer, int $orderId): ?array
    {
        return null; // À BRANCHER
    }
}
