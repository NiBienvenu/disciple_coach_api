# Seed data (source unique pour l’API)

Emplacement : `database/seeders/demo/`

| Fichier | Seeder |
|---------|--------|
| `curriculum_seed.json` | `CurriculumSeeder` |
| `demo_seed.json` | `DemoDataSeeder` |
| `track_*.json` + `merge.py` | régénérer le curriculum |

```bash
# régénérer curriculum_seed.json depuis les tracks
cd database/seeders/demo
python3 merge.py

# charger en base
cd ../../..
php artisan migrate:fresh --seed
```

Le dossier Flutter `disciple_coach/seed/` a été retiré : une seule source de vérité.
