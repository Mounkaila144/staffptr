# 11. Pièces jointes privées

## 11.1 Stockage — A-04 / NFR15

Disque `private` pointant sur `storage/app/private/`, **hors de la racine web**. Le serveur web
(Apache 2.4, DEC-13) ne sert jamais ce répertoire, et sa configuration porte un `Require all denied`
explicite sur `/storage` — la protection ne repose pas seulement sur le fait que le chemin est en
dehors de `public/`.

Chemin de stockage : `private/{module}/{annee}/{mois}/{ulid}.{ext}`. Le nom d'origine est conservé
**en base**, jamais sur le disque : un nom de fichier fourni par l'utilisateur ne doit jamais devenir
un chemin.

## 11.2 Contrôle d'accès à la lecture

Aucun fichier n'est accessible par URL devinable. Deux modes :

| Mode | Usage | Mécanisme |
|---|---|---|
| **Contrôlé** | Justificatifs financiers, documents du dossier personnel (FR17) | Route → Policy → `X-Sendfile` (`mod_xsendfile`, DEC-13) vers un chemin borné par `XSendFilePath` |
| **Signé** | Vignettes de preuves en liste | URL signée Laravel, validité 10 minutes |

`X-Sendfile` fait porter la transmission par Apache après que PHP a validé l'autorisation :
on garde le contrôle applicatif **et** l'efficacité du serveur web. Sur 3G, faire transiter un
justificatif de 3 Mo par PHP-FPM immobiliserait un ouvrier pour toute la durée du téléchargement.
Côté Laravel, `BinaryFileResponse::trustXSendfileTypeHeader()` active ce mécanisme sans changer le
contrôleur.

## 11.3 Validation au téléversement — NFR16 / Q11

Refus **côté serveur**, indépendamment de tout contrôle côté client :

1. Type MIME déterminé **par le contenu** (`finfo`), jamais par l'extension ni par l'en-tête client.
2. Liste blanche et taille maximale lues dans les paramètres (FR25), modifiables sans code.
3. Extension réécrite depuis le type MIME validé.
4. Images : ré-encodage systématique par Intervention Image — supprime les métadonnées EXIF et
   neutralise toute charge utile dissimulée.
5. Vignette générée en file d'attente (UX § 11.2). Aucune image pleine résolution en liste.

> **DEC-08 — proposition pour Q11 :** `pdf`, `jpeg`, `png`, `webp`, `heic`, **8 Mo maximum**.
> HEIC est indispensable : c'est le format par défaut des photos iPhone, et un justificatif refusé
> silencieusement est un justificatif jamais fourni. Il est converti en JPEG à l'ingestion.

## 11.4 Sauvegarde et volumétrie

Les pièces jointes entrent dans la sauvegarde quotidienne (§ 21). Hypothèse de dimensionnement :
100 utilisateurs × ~5 pièces/mois × ~1,5 Mo ≈ **9 Go/an** après ré-encodage. Sur 10 ans (NFR26,
DEC-11), ~90 Go : un volume qu'un VPS absorbe sans architecture de stockage dédiée.

---
