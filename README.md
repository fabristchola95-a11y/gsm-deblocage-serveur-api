# API revendeurs — GSM Déblocage Serveur

Connectez votre site ou votre logiciel à **GSM Déblocage Serveur** : services, prix, commandes automatiques et résultats.

> **Guide complet (connexion, tests, exemples) : https://gsmdeblocageserveur.com/documentation**

**En 4 étapes :** 1. créer sa clé API → 2. connecter son site (Dhru ou sur mesure) → 3. tester la connexion avec le testeur en ligne → 4. commander et recevoir les résultats.

- Documentation en ligne + testeur : https://gsmdeblocageserveur.com/v2/api_docs.php
- Obtenir sa clé API : https://gsmdeblocageserveur.com/v2/api_access.php

| Situation | Format | Adresse |
| --- | --- | --- |
| Site Dhru Fusion / GSM Theme ou logiciel compatible | Dhru Fusion | URL à saisir : `https://gsmdeblocageserveur.com/public` (point d'accès : `/public/api/index.php`) |
| Site / application sur mesure | REST JSON | `https://gsmdeblocageserveur.com/api.php` |

Les deux formats utilisent la même clé, le même solde et les mêmes commandes. Seuls les services dont le prix est publié sont disponibles.

## Client prêt à l'emploi (comme GSM Theme)

Copiez le client dans votre projet, mettez vos identifiants dans `.env`, c'est tout.

| Fichier | Pour |
| --- | --- |
| [`app/Http/Controllers/GsmDeblocageApiController.php`](app/Http/Controllers/GsmDeblocageApiController.php) | **Laravel** : `getAccountInfo`, `getServiceList`, `placeOrder`, `getOrder` |
| [`routes/web.php`](routes/web.php) | Routes de test Laravel (`/api-test/...`) |
| [`php-client/GsmDeblocageApi.php`](php-client/GsmDeblocageApi.php) | **PHP sans framework** : `accountInfo()`, `serviceList()`, `placeOrders()`, `orderStatus()` |
| [`php-client/example.php`](php-client/example.php) | Exemple complet à lancer en ligne de commande |

```env
GSMDS_API_URL=https://gsmdeblocageserveur.com/
GSMDS_API_USERNAME=votre@email.com
GSMDS_API_KEY=VOTRE_CLE_API
```

Compatible avec les clients **GSM Theme** existants : remplacez seulement l'URL par `https://gsmdeblocageserveur.com/`, votre e-mail et votre clé.

## 1. Format Dhru Fusion

`POST https://gsmdeblocageserveur.com/public/api/index.php` (formulaire). L'ancienne adresse `/api/index.php` reste valable.

| Champ | Valeur |
| --- | --- |
| `username` | e-mail du compte |
| `apiaccesskey` | clé API (ou token) |
| `action` | voir ci-dessous |
| `requestformat` | `JSON` |
| `parameters` | XML `<PARAMETERS>…</PARAMETERS>`, JSON, ou l'un des deux en base64 |

| Action | Paramètres | Rôle |
| --- | --- | --- |
| `accountinfo` | — | Solde, devise, e-mail |
| `imeiservicelist` | — | Services par famille : `CREDIT`, `TIME`, `Requires.Custom` |
| `serverservicelist` | — | Services serveur |
| `getimeiservicedetails` | `ID` | Détail d'un service |
| `placeimeiorder` | `ID`, `IMEI`, `SN`, `QNT`, `CUSTOMFIELD` | Commander → `REFERENCEID` |
| `getimeiorder` | `ID` | Statut + résultat |
| `placeimeiorderbulk` | JSON base64 (≤ 100) | Commandes groupées |
| `placebulkorder` | JSON base64 `{"7":{"ID":25,"QNT":1,"CUSTOMFIELD":"…"}}` | Commandes groupées (format GSM Theme) → `SUCCESS.7.referenceid` |
| `getimeiorderbulk` | JSON base64 | Statuts groupés |

`CUSTOMFIELD` = objet JSON encodé en base64, clés = `fieldname` de `Requires.Custom`.

| STATUS | Signification |
| --- | --- |
| 0 | Reçue, en attente |
| 1 | En cours |
| 3 | Rejetée — remboursée, motif dans `CODE` |
| 4 | Terminée — résultat dans `CODE` |

### Ajouter le fournisseur dans un site Dhru Fusion
1. Settings → API Settings → Add API, type **Dhru Fusion**.
2. URL `https://gsmdeblocageserveur.com/public`, Username = e-mail, API Access Key = clé.
3. Sync / Import services, puis liez vos services.

## 2. API REST JSON

En-tête `X-API-Key: VOTRE_CLE_API` (ou `X-API-Token`).

| Méthode | Action | Rôle |
| --- | --- | --- |
| GET | `?action=balance` | Solde |
| GET | `?action=services` | Liste des services |
| GET | `?action=service&id=123` | Détail + champs requis |
| POST | `?action=order` | Commander (JSON) |
| GET | `?action=status&order_id=1001` | Statut + résultat |
| POST | `?action=refund` | Remboursement d'une commande échouée |

```json
{"service_id": 123, "imei": "356789012345678", "fields": {"model": "SM-A525F"}, "reference": "MA-REF-1"}
```

## 3. Tests

Les scripts lisent vos identifiants dans des variables d'environnement (ne les écrivez jamais dans un fichier du dépôt) :

```bash
export GSM_USER="votre@email.com"
export GSM_KEY="VOTRE_CLE_API"
php tests/dhru_readonly_test.php      # lecture seule, gratuit
php tests/rest_readonly_test.php      # lecture seule, gratuit
bash examples/curl_examples.sh
```

`examples/dhru_place_order.php` passe une **vraie commande payée** : lancez-le seulement avec un service et un IMEI réels.

## Sécurité
- Clé côté serveur uniquement, jamais dans du JavaScript public ni dans un dépôt.
- Clé compromise : régénérez-la dans Accès API (2 fois par heure maximum).
- Interrogez un statut au plus une fois par minute par commande.

Support : WhatsApp +243 89 40 97 937 — support@gsmdeblocageserveur.com
