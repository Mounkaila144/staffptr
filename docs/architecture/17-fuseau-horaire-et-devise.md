# 17. Fuseau horaire et devise

## 17.1 Temps — NFR23 / DEC-01

| Élément | Choix |
|---|---|
| Stockage des horodatages | **UTC** (`config('app.timezone') = 'UTC'`) |
| Affichage | **`Africa/Niamey`** (UTC+1, sans heure d'été, invariable) |
| Dates métier (`date_rapport`, `date_depense`, mois de clôture) | Colonnes `DATE`, **calendrier civil Niamey, jamais converties** |
| Fuseau MySQL | UTC |
| Fuseau système du VPS | UTC |

La distinction entre horodatage et date métier est le point à ne pas manquer. « Le rapport du
18 juillet » est une date civile de Niamey : la convertir en UTC la ferait basculer d'un jour aux
heures tardives, et l'heure limite de 17 h 45 (RM-07) tombe précisément dans une plage où une
conversion mal placée décale la date.

**Conséquences opérationnelles :**
- L'heure limite du rapport (17 h 45) est **évaluée en heure de Niamey**.
- Les tâches planifiées portent `->timezone('Africa/Niamey')` explicitement.
- Le formatage d'affichage passe par un helper unique, côté serveur, jamais recopié.
- Le délai de 24 h de FR112 (encaissement à enregistrer) se calcule sur des horodatages UTC.

> **DEC-01.** Un stockage direct en `Africa/Niamey` serait défendable — le Niger n'a jamais appliqué
> d'heure d'été et l'offset est fixe. UTC est recommandé : c'est le défaut Laravel, tout l'outillage
> (journaux, sauvegardes, MySQL) le suppose, et l'écart de coût est nul. Votre arbitrage.

## 17.2 Devise — RM-02 / NFR22

- **Entiers XOF, aucune décimale**, `BIGINT UNSIGNED`. Aucun `DECIMAL`, aucun `float`, à aucun
  endroit du système, y compris dans les calculs intermédiaires de parts et de réserve.
- Aucune conversion de devise, aucun taux de change : l'application est mono-devise.
- Formatage unique : séparateur de milliers espace insécable fine, suffixe `FCFA` → `1 250 000 FCFA`.
- Le formatage est fait **côté serveur** par `App\Support\Money`. `Intl.NumberFormat` côté navigateur
  est écarté : le rendu du groupement varie selon les locales embarquées d'Android, et un montant
  financier affiché différemment selon l'appareil est un défaut.

---
