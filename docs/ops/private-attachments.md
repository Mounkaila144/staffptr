# Pièces jointes privées — prérequis Apache et images

Les pièces jointes sont écrites dans `storage/app/private`, jamais dans `public/`. La production
doit conserver une seconde barrière Apache même si le `DocumentRoot` pointe déjà vers `public/`.

## Protection du répertoire

Dans la configuration du vhost, adapter le chemin absolu puis recharger Apache :

```apache
<Directory "/var/www/ptrstaff/current/storage">
    Require all denied
</Directory>
```

Cette règle s'applique à tout `/storage` et ne doit pas être remplacée par une règle portant
uniquement sur `storage/app/private`.

## X-Sendfile après autorisation Laravel

Installer et activer `mod_xsendfile` et `mod_headers`, puis ajouter au vhost :

```apache
XSendFile On
XSendFilePath "/var/www/ptrstaff/shared/storage/app/private"
RequestHeader set X-Sendfile-Type "X-Sendfile"
```

Définir `ATTACHMENT_X_SENDFILE=true` en production. Laravel vérifie d'abord la session, la
permission et la policy de l'objet rattaché ; Apache sert ensuite le chemin transmis. En local et
en CI, garder cette option à `false` afin que Symfony utilise son flux de repli.

Après déploiement, vérifier qu'une URL directe vers `/storage/app/private/...` répond `403` ou
`404`, qu'un utilisateur hors portée reçoit `403` sur la route contrôlée et qu'une lecture autorisée
porte l'en-tête de réponse `X-Sendfile`.

## HEIC indispensable

Le format iPhone HEIC exige l'extension PHP `imagick` compilée avec `libheif`, sur le serveur web
comme sur les workers de file et en CI. Vérifier avec :

```bash
php -m | grep -i imagick
php -r '$i = new Imagick; var_export(in_array("HEIC", $i->queryFormats("HEIC"), true));'
```

Sans ce prérequis, JPEG, PNG et WebP restent disponibles via GD, mais l'application refuse HEIC
avec un message explicite au lieu de stocker un fichier non réencodé.

Configurer également `upload_max_filesize` et `post_max_size` au-dessus de la limite maximale
autorisée dans les paramètres de l'application ; sinon PHP interromprait la requête avant que le
message métier puisse afficher la taille et la limite réelles.
