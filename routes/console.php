<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('ptr:send-expense-approval-reminders')
    ->dailyAt('08:00')
    ->timezone('Africa/Niamey')
    ->withoutOverlapping();

Schedule::command('ptr:send-daily-report-reminders')
    ->everyMinute()
    ->timezone('Africa/Niamey')
    ->withoutOverlapping();

// L'émission est elle-même idempotente : `withoutOverlapping` évite le chevauchement, et le
// marquage de livraison sous verrou empêche tout doublon si l'exécution est rejouée.
Schedule::command('ptr:deliver-support-request-batches')
    ->everyFiveMinutes()
    ->timezone('Africa/Niamey')
    ->withoutOverlapping();

// Niveau d'alerte financière : recalcul quotidien, et relance du plan correctif tant que le mois
// est orange sans plan. Le recalcul est idempotent et ne touche jamais un mois clos (AC 5, AC 7).
Schedule::command('ptr:recalculate-alert-level')
    ->dailyAt('06:00')
    ->timezone('Africa/Niamey')
    ->withoutOverlapping();

// Objectifs proches de l'échéance : un rappel par objectif et par jour civil (FR31).
Schedule::command('ptr:send-objective-deadline-reminders')
    ->dailyAt('07:00')
    ->timezone('Africa/Niamey')
    ->withoutOverlapping();

// Fins de contrat et de stage : consomme le service exposé par la story 3.2, qui n'émettait rien
// faute de centre de notifications à ce jalon (AC 33).
Schedule::command('ptr:send-contract-ending-reminders')
    ->dailyAt('07:15')
    ->timezone('Africa/Niamey')
    ->withoutOverlapping();

// Rapprochement bancaire et rapport financier mensuel à préparer (FR31).
Schedule::command('ptr:send-financial-preparation-reminders')
    ->dailyAt('07:30')
    ->timezone('Africa/Niamey')
    ->withoutOverlapping();

// Invariants de sécurité (story 10.1 AC 19, AC 24). L'exécution est quotidienne et son échec est
// un signal d'exploitation : la commande sort en code d'erreur, ce que la supervision transforme
// en alerte. Le contrôle est en lecture seule — il constate, il ne corrige rien.
Schedule::command('ptr:check-invariants')
    ->dailyAt('05:30')
    ->timezone('Africa/Niamey')
    ->withoutOverlapping();

// ── Story 11.1 — exploitation ────────────────────────────────────────────────────────────────

// Sauvegarde chiffrée quotidienne à 02 h 00 (AC 1). L'heure creuse limite l'effet du dump sur un
// VPS partagé ; `--single-transaction` fait le reste.
Schedule::command('backup:clean')
    ->dailyAt('01:45')
    ->timezone('Africa/Niamey')
    ->withoutOverlapping();

Schedule::command('backup:run')
    ->dailyAt('02:00')
    ->timezone('Africa/Niamey')
    ->withoutOverlapping();

// Fraîcheur de la sauvegarde (AC 6, AC 22). Le contrôle tourne **après** l'heure de sauvegarde :
// le vérifier avant reviendrait à valider celle de la veille.
Schedule::command('backup:monitor')
    ->dailyAt('03:00')
    ->timezone('Africa/Niamey')
    ->withoutOverlapping();

// Test de restauration mensuel (AC 13). Une sauvegarde jamais restaurée n'est pas une sauvegarde,
// c'est un fichier. Le premier jour du mois, après la sauvegarde de la nuit.
Schedule::command('ptr:test-restore')
    ->monthlyOn(1, '04:00')
    ->timezone('Africa/Niamey')
    ->withoutOverlapping();
