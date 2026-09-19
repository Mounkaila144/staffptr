<?php

use App\Notifications\BlockerNotification;
use App\Notifications\CorrectionPlanReminderNotification;
use App\Notifications\DailyReportReminderNotification;
use App\Notifications\ExpenseApprovalReminderNotification;
use App\Notifications\ExpenseRequestedNotification;
use App\Notifications\GroupedSupportRequestNotification;

return [

    /*
    |---------------------------------------------------------------------------
    | Canal WhatsApp — liste blanche des types autorisés
    |---------------------------------------------------------------------------
    |
    | WhatsApp n'est pas un canal neutre. L'application y accède par Evolution
    | API, un client non officiel : un volume élevé, envoyé en rafale à des
    | destinataires qui n'ont jamais écrit au numéro, expose ce numéro à un
    | blocage. Un blocage coûterait cher — c'est aussi le canal du code de
    | réinitialisation de mot de passe, seul chemin de récupération de compte.
    |
    | La règle est donc l'inverse de l'intuition : **WhatsApp est l'exception**,
    | le centre de notifications de l'application est la norme. Toute
    | notification est écrite en base et visible dans l'application ; seules
    | celles listées ici sortent aussi sur WhatsApp.
    |
    | Le critère d'inscription : la personne doit agir **hors de
    | l'application**, et un retard aurait une conséquence réelle. Une
    | information qu'elle verra de toute façon en ouvrant l'écran concerné n'a
    | rien à faire ici.
    |
    | Un type absent de cette liste n'est pas perdu : il reste notifié dans
    | l'application. Ajouter ou retirer une ligne suffit, aucun code à toucher.
    |
    */

    'whatsapp' => [

        'enabled' => [
            // Cœur du produit : la personne doit rendre son rapport avant
            // l'heure limite, et n'est par définition pas dans l'application.
            DailyReportReminderNotification::class,

            // Un blocage est urgent par définition — c'est sa raison d'être.
            BlockerNotification::class,

            // Direction seule, quelques messages par jour : une dépense en
            // attente immobilise une décision.
            ExpenseRequestedNotification::class,
            ExpenseApprovalReminderNotification::class,

            // Direction seule : le plan correctif est dû sous 48 heures.
            CorrectionPlanReminderNotification::class,

            // Déjà groupée par conception (epic 7) : un message par tuteur et
            // par créneau, jamais un par demande.
            GroupedSupportRequestNotification::class,
        ],

        /*
        |-----------------------------------------------------------------------
        | Étalement des envois
        |-----------------------------------------------------------------------
        |
        | Sans étalement, le rappel de rapport quotidien part en une seule
        | boucle : cinquante messages quasi identiques dans la même minute,
        | depuis un numéro qui n'envoyait rien la veille. C'est le signal qui
        | déclenche le plus sûrement un blocage.
        |
        | Chaque envoi réserve un créneau ; le suivant attend l'intervalle. À
        | quatre par minute, cinquante rappels s'étalent sur environ douze
        | minutes — invisible pour l'usage, décisif pour la réputation du
        | numéro.
        |
        | `max_delay_seconds` borne l'attente : au-delà, un rappel arriverait
        | trop tard pour servir à quelque chose.
        |
        */

        'pace' => [
            'per_minute' => (int) env('WHATSAPP_PER_MINUTE', 4),
            'max_delay_seconds' => (int) env('WHATSAPP_MAX_DELAY_SECONDS', 1800),
        ],
    ],

];
