@extends('site.layout')
@section('title', 'Politique de confidentialité · DiscipleCoach')
@section('content')
<h1>Politique de confidentialité</h1>
<p class="muted">Dernière mise à jour : 25 novembre 2026</p>

<p>DiscipleCoach est une application mobile de Campus pour Christ au Burundi. Elle aide les coachs à accompagner les disciples avec des leçons, des quiz et des messages. Cette page explique quelles données nous collectons, pourquoi, et comment les supprimer.</p>

<h2>1. Responsable et contact</h2>
<p>Campus pour Christ au Burundi. Pour toute question sur vos données : <a href="mailto:emile.manirambona@zafcpc.org">emile.manirambona@zafcpc.org</a>.</p>

<h2>2. Les données que nous collectons</h2>
<table>
  <tr><th>Donnée</th><th>Pourquoi</th></tr>
  <tr><td>Nom, adresse e-mail, mot de passe (chiffré)</td><td>Créer votre compte et vous connecter</td></tr>
  <tr><td>Téléphone et photo de profil (facultatifs)</td><td>Compléter votre profil</td></tr>
  <tr><td>Rôle (Disciple, Coach, Admin) et langue choisie</td><td>Afficher le bon contenu</td></tr>
  <tr><td>Progression : leçons, quiz, scores, objectifs, certificats, notes</td><td>Suivre votre parcours</td></tr>
  <tr><td>Réponses aux questions de foi dans les leçons</td><td>Permettre à votre coach de vous accompagner</td></tr>
  <tr><td>Messages avec votre coach ou disciple : texte, photos, fichiers et messages vocaux</td><td>Échanger dans l'application</td></tr>
  <tr><td>Jeton de notification de l'appareil</td><td>Vous envoyer des notifications</td></tr>
</table>
<p>L'application utilise l'appareil photo, la galerie et le micro <b>seulement</b> quand vous choisissez d'envoyer une photo, un fichier ou un message vocal. Nous ne collectons pas votre position, vos contacts ni vos données bancaires.</p>

<h2>3. Qui voit vos données</h2>
<ul>
  <li><b>Disciple :</b> voit son propre profil et sa progression.</li>
  <li><b>Coach :</b> voit le profil, la progression et les messages des disciples qu'il accompagne, et seulement eux.</li>
  <li><b>Admin :</b> gère les comptes, approuve les coachs et gère les contenus.</li>
</ul>

<h2>4. Stockage et sécurité</h2>
<p>Vos données sont stockées sur notre propre serveur. Les échanges entre l'application et le serveur sont chiffrés (HTTPS) et l'accès est limité selon le rôle. Nous utilisons Firebase Cloud Messaging (Google) uniquement pour envoyer les notifications. Voir la <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">politique de confidentialité de Google</a>.</p>

<h2>5. Partage</h2>
<p>Nous ne vendons pas vos données et ne les utilisons pas pour de la publicité. Nous les partageons seulement avec votre coach ou vos disciples, avec Google pour les notifications, ou si la loi nous y oblige.</p>

<h2>6. Conservation et suppression</h2>
<p>Nous gardons vos données tant que votre compte est actif. Vous pouvez demander la suppression de votre compte à tout moment sur la page <a href="{{ route('account.delete') }}">Supprimer mon compte</a> ou par e-mail. Nous traitons la demande dans un délai de 30 jours. Votre compte, votre profil, votre progression, vos messages et vos fichiers sont alors effacés définitivement.</p>

<h2>7. Vos droits</h2>
<p>Vous pouvez voir, corriger ou supprimer vos données, et fermer votre compte quand vous voulez. Écrivez-nous et nous répondons sous 30 jours.</p>

<h2>8. Enfants</h2>
<p>DiscipleCoach n'est pas fait pour les enfants de moins de 13 ans. Si un enfant de moins de 13 ans a créé un compte, écrivez-nous et nous le supprimerons.</p>

<h2>9. Changements</h2>
<p>En cas de changement important de cette politique, nous vous préviendrons dans l'application.</p>
@endsection
