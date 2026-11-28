<?php

/*
    Client API GSM Déblocage Serveur pour Laravel (format GSM Theme / Dhru Fusion).
    Site    : https://gsmdeblocageserveur.com
    Adresse : https://gsmdeblocageserveur.com/public  ->  /public/api/index.php
    Support : WhatsApp +243 89 40 97 937 · support@gsmdeblocageserveur.com

    Installation :
      1. Copiez ce fichier dans app/Http/Controllers/
      2. Copiez routes/web.php (ou ajoutez les 4 routes dans le vôtre)
      3. Ajoutez vos identifiants dans .env :
            GSMDS_API_URL=https://gsmdeblocageserveur.com/
            GSMDS_API_USERNAME=votre@email.com
            GSMDS_API_KEY=VOTRE_CLE_API
      4. Ouvrez /api-test/get-account dans votre navigateur pour tester.

    Votre clé API : Mon compte -> Accès API -> « Générer ma clé API » (envoyée aussi par e-mail).
    Ne mettez jamais votre clé directement dans ce fichier ni dans un dépôt Git.
*/

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;

class GsmDeblocageApiController extends Controller
{
    /*
     * Identifiants lus dans le fichier .env
     */
    private function credentials(): array
    {
        return [
            'url'          => env('GSMDS_API_URL', 'https://gsmdeblocageserveur.com/'),
            'username'     => env('GSMDS_API_USERNAME', ''),
            'apiaccesskey' => env('GSMDS_API_KEY', ''),
        ];
    }

    /*
     * Appel commun à toutes les fonctions.
     * - POST (formulaire) vers https://gsmdeblocageserveur.com/public/api/index.php
     * - parameters = base64(JSON) pour les actions groupées
     * - Réponse toujours en JSON, avec "SUCCESS" ou "ERROR" et "apiversion"
     */
    private function intGsmDeblocageApi(string $action, ?array $para = null): array
    {
        $api = $this->credentials();
        $parsed = parse_url($api['url']);
        $url = $parsed['scheme'] . '://' . $parsed['host'] . '/public/api/index.php';

        $jsonParams = $para ? base64_encode(json_encode($para)) : '';

        try {
            $response = Http::timeout(120)
                ->connectTimeout(15)
                ->retry(2, 2000)
                ->withHeaders([
                    'User-Agent' => request()->header('User-Agent', 'LaravelClient/1.0'),
                ])
                ->asForm()
                ->post($url, [
                    'username'      => $api['username'],
                    'apiaccesskey'  => $api['apiaccesskey'],
                    'action'        => $action,
                    'requestformat' => 'JSON',
                    'parameters'    => $jsonParams,
                ]);

            if ($response->failed()) {
                return [
                    'ERROR' => [
                        ['MESSAGE' => $response->body(), 'STATUS' => $response->status()],
                    ],
                ];
            }

            return $response->json() ?? ['ERROR' => [['MESSAGE' => 'Réponse invalide du serveur']]];
        } catch (\Throwable $e) {
            return [
                'ERROR' => [
                    ['MESSAGE' => $e->getMessage()],
                ],
            ];
        }
    }

    // SOLDE DU COMPTE
    public function getAccountInfo()
    {
        $response = $this->intGsmDeblocageApi('accountinfo');
        return $response;

        /* Exemple de réponse
            {
                "SUCCESS": [
                    {
                        "MESSAGE": "Your Account Info",
                        "AccountInfo": {
                            "credit": "50.00 USD",
                            "creditraw": 50,
                            "mail": "votre@email.com",
                            "currency": "USD"
                        }
                    }
                ],
                "apiversion": "6.1"
            }

           Erreur (e-mail ou clé incorrects, clé régénérée, API désactivée)
            {
                "ERROR": [
                    { "MESSAGE": "Authentication Failed" }
                ],
                "apiversion": "6.1"
            }
        */
    }

    // LISTE DES SERVICES
    public function getServiceList()
    {
        $response = $this->intGsmDeblocageApi('imeiservicelist');
        return $response;

        /* Exemple de réponse
           - CREDIT : votre prix en USD
           - TIME   : délai de traitement
           - Services SERVER : les champs à envoyer sont dans "Requires.Custom" (fieldname)
           - Services IMEI   : le champ principal est décrit dans "CUSTOM" (customname, customlen, maxlength)
           Seuls les services dont le prix est publié apparaissent dans cette liste.
            {
                "SUCCESS": [
                    {
                        "MESSAGE": "IMEI Service List",
                        "LIST": {
                            "UnlockTool": {
                                "GROUPNAME": "UnlockTool",
                                "GROUPTYPE": "SERVER",
                                "SERVICES": {
                                    "25": {
                                        "SERVICEID": 25,
                                        "SERVICETYPE": "SERVER",
                                        "SERVICENAME": "UnlockTool 1 Year Activation",
                                        "CREDIT": "30.00",
                                        "TIME": "1-24h",
                                        "INFO": "",
                                        "QNT": "1",
                                        "MINQNT": "1",
                                        "MAXQNT": "1",
                                        "Requires.SN": "None",
                                        "Requires.Custom": [
                                            {
                                                "type": "serviceimei",
                                                "fieldname": "username",
                                                "fieldtype": "text",
                                                "description": "Username",
                                                "fieldoptions": "",
                                                "required": "on"
                                            }
                                        ]
                                    }
                                }
                            },
                            "Vérification IMEI & GSX": {
                                "GROUPNAME": "Vérification IMEI & GSX",
                                "GROUPTYPE": "IMEI",
                                "SERVICES": {
                                    "123": {
                                        "SERVICEID": 123,
                                        "SERVICETYPE": "IMEI",
                                        "SERVICENAME": "ITEL | INFINIX | TECNO | SONIM INFO",
                                        "CREDIT": "0.70",
                                        "TIME": "Instant",
                                        "INFO": "",
                                        "QNT": "0",
                                        "MINQNT": "1",
                                        "MAXQNT": "1",
                                        "Requires.SN": "None",
                                        "Requires.Custom": [],
                                        "CUSTOM": {
                                            "allow": "1",
                                            "bulk": "0",
                                            "customname": "IMEI or SN",
                                            "custominfo": "IMEI or SN",
                                            "customlen": "8",
                                            "maxlength": "18",
                                            "regex": "",
                                            "isalpha": "1"
                                        }
                                    }
                                }
                            }
                        },
                        "ACCOUNTINFO": {
                            "credit": "50.00 USD",
                            "creditraw": 50,
                            "mail": "votre@email.com",
                            "currency": "USD"
                        }
                    }
                ],
                "apiversion": "6.1"
            }
        */
    }

    // PASSER DES COMMANDES (jusqu'à 100 par appel)
    public function placeOrder()
    {
        $orders = [
            0 => [
                'id'            => 7,      // votre numéro de commande (il revient dans la réponse)
                'api_server_id' => 25,     // SERVICEID chez GSM Déblocage Serveur
                'name'          => 'UnlockTool 1 Year Activation',
                'qnt'           => 1,
                'fields'        => [       // les "fieldname" de Requires.Custom
                    'username' => 'identifiant_unlocktool',
                ],
            ],
            1 => [
                'id'            => 8,
                'api_server_id' => 123,    // service IMEI
                'name'          => 'ITEL | INFINIX | TECNO | SONIM INFO',
                'qnt'           => 1,
                'fields'        => [       // service IMEI : envoyez l'IMEI (ou le n° de série) sous la clé "IMEI"
                    'IMEI' => '356789012345678',
                ],
            ],
        ];

        $params = [];
        foreach ($orders as $order) {
            $params[$order['id']]['ID'] = $order['api_server_id'];
            $params[$order['id']]['QNT'] = $order['qnt'];
            $params[$order['id']]['CUSTOMFIELD'] = base64_encode(json_encode($order['fields'] ?? []));
        }

        $response = $this->intGsmDeblocageApi('placebulkorder', $params);
        return $response;

        /* Exemple de réponse
           - Le montant est débité de votre solde au moment de la commande.
           - Gardez "referenceid" : il sert à suivre la commande avec getOrder().
           - Les commandes refusées sont aussi dans "SUCCESS", avec "status": "error".
            {
                "SUCCESS": {
                    "7": {
                        "status": "success",
                        "message": "Order received",
                        "referenceid": 1001   // à enregistrer dans votre base
                    },
                    "8": {
                        "status": "error",
                        "message": "Solde insuffisant."
                    }
                },
                "apiversion": "6.1"
            }

           Messages d'erreur possibles : "Champ obligatoire manquant : Username.", "Solde insuffisant.",
           "Service introuvable ou inactif.", "Authentication Failed".
        */
    }

    // STATUT ET RÉSULTAT DES COMMANDES
    public function getOrder()
    {
        $orders = [
            0 => [
                'id'          => 7,       // votre numéro de commande
                'referenceid' => 1001,    // le "referenceid" reçu avec placeOrder()
            ],
            1 => [
                'id'          => 8,
                'referenceid' => 1002,
            ],
        ];

        $params = [];
        foreach ($orders as $order) {
            $params[$order['id']]['ID'] = $order['referenceid'];
        }

        $response = $this->intGsmDeblocageApi('getimeiorderbulk', $params);
        return $response;

        /* Exemple de réponse
           STATUS : 0 = en attente · 1 = en cours · 3 = rejetée (montant remboursé) · 4 = terminée
           CODE   : le résultat du service (STATUS 4) ou le motif du rejet (STATUS 3)
           Interrogez au plus une fois par minute et par commande.
            {
                "7": {
                    "SUCCESS": [
                        {
                            "STATUS": "4",
                            "CODE": "Model: SM-A525F\nFRP: OFF",
                            "COMMENTS": ""
                        }
                    ]
                },
                "8": {
                    "SUCCESS": [
                        {
                            "STATUS": "1",
                            "CODE": "",
                            "COMMENTS": ""
                        }
                    ]
                },
                "apiversion": "6.1"
            }
        */
    }

    /*
     * OUTIL : liste des services à plat, prête à enregistrer dans votre base.
     * Retourne [['id' => 25, 'type' => 'SERVER', 'group' => 'UnlockTool', 'name' => '...', 'price' => 30.0, 'time' => '1-24h',
     *            'fields' => ['username' => true], 'imei_label' => null], ...]
     */
    public function flatServiceList(): array
    {
        $flat = [];
        $data = $this->intGsmDeblocageApi('imeiservicelist');

        foreach ($data['SUCCESS'][0]['LIST'] ?? [] as $group) {
            foreach ($group['SERVICES'] ?? [] as $s) {
                $fields = [];
                foreach ($s['Requires.Custom'] ?? [] as $f) {
                    $fields[$f['fieldname']] = ($f['required'] ?? '') === 'on';
                }
                $flat[] = [
                    'id'         => (int) $s['SERVICEID'],
                    'type'       => $s['SERVICETYPE'],
                    'group'      => $group['GROUPNAME'],
                    'name'       => $s['SERVICENAME'],
                    'price'      => (float) $s['CREDIT'],
                    'time'       => $s['TIME'],
                    'fields'     => $fields,
                    'imei_label' => $s['CUSTOM']['customname'] ?? null,
                ];
            }
        }

        return $flat;
    }

    /*
     * OUTIL : traduit une réponse getOrder() en résultats simples.
     * Retourne [7 => ['done' => true, 'rejected' => false, 'result' => '...'], ...]
     * À lancer par une tâche planifiée (php artisan schedule) toutes les 5 minutes.
     */
    public function readStatuses(array $response): array
    {
        $out = [];
        foreach ($response as $ref => $item) {
            if ($ref === 'apiversion' || !is_array($item)) {
                continue;
            }
            $row = $item['SUCCESS'][0] ?? [];
            $status = (int) ($row['STATUS'] ?? 0);
            $out[$ref] = [
                'done'     => $status === 4,
                'rejected' => $status === 3,
                'result'   => (string) ($row['CODE'] ?? ''),
            ];
        }

        return $out;
    }
}
