# DiscipleCoach API

## Mise en route (équipe)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --force   # crée aussi les rôles, l'admin et le programme
```

### Clé Firebase pour les notifications

La clé de compte de service n'est pas dans le dépôt, et ne doit jamais y
entrer : c'est un mot de passe qui ouvre le projet Firebase.

1. Demandez le fichier à un membre de l'équipe (gestionnaire de mots de
   passe ou canal privé), ou générez le vôtre dans la console Firebase :
   Paramètres du projet > Comptes de service > Générer une nouvelle clé privée.
2. Rangez-le hors du dossier public, par exemple
   `storage/app/accountfirebase.json`.
3. Donnez son chemin dans `.env` :

```
FCM_CREDENTIALS=/chemin/absolu/vers/accountfirebase.json
```

Sans cette variable, le code retombe sur `public/app/accountfirebase.json`,
qui est protégé par un `.htaccess`. Ne supprimez pas ce `.htaccess` : sans lui,
n'importe qui peut télécharger la clé depuis le site.

### Déploiement

```bash
git pull origin develop
composer install --no-dev -o
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache && php artisan route:cache
```

Vérification rapide : `GET /api/v1/curriculum/revision` doit renvoyer un
numéro. Un 404 veut dire que le serveur tourne encore sur l'ancien code.

---

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).


Ajuste les contne de l application faire utilise @content/  on y trouve des contenu a mettre dans l 'application , faire Parcours pour les identifier enfin de les mettre dans le meilleur emplacements , 
Pour le quiz faire que se soit sur les niveau , ces questions provient dans le contenu du cours , une fois on parvient de reussir faire les questions , faire genere un certifiat a telecharge 
