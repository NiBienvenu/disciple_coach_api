@extends('site.layout')
@section('title', 'Supprimer mon compte · DiscipleCoach')
@section('content')
<h1>Supprimer mon compte DiscipleCoach</h1>
<p>Application DiscipleCoach, éditée par Campus pour Christ au Burundi.</p>

@if (session('sent'))
  <div class="ok"><b>Demande reçue.</b> Si un compte existe avec cette adresse, il sera supprimé dans un délai de 30 jours. Nous pouvons vous écrire pour confirmer que la demande vient bien de vous.</div>
@endif

<h2>Comment faire</h2>
<ol>
  <li>Remplissez le formulaire ci-dessous avec le nom et l'e-mail de votre compte.</li>
  <li>Nous vérifions que la demande vient bien de vous (par e-mail si besoin).</li>
  <li>Votre compte et vos données sont supprimés sous 30 jours.</li>
</ol>

<h2>Ce qui est supprimé</h2>
<p>Votre compte (nom, e-mail, téléphone, photo), votre progression (leçons, quiz, scores, objectifs, certificats, notes, réponses), vos messages et fichiers envoyés, et vos jetons de notification. Rien n'est gardé, sauf si la loi nous oblige à garder une information.</p>
<p class="muted">Nous gardons seulement cette demande de suppression (nom, e-mail, date) pendant 12 mois, pour prouver qu'elle a été traitée.</p>

<form method="POST" action="{{ route('account.delete.store') }}" class="card" style="margin-top:24px">
  @csrf
  <label for="name">Nom complet</label>
  <input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="255">
  @error('name')<div class="error">{{ $message }}</div>@enderror

  <label for="email">E-mail du compte</label>
  <input type="email" id="email" name="email" value="{{ old('email') }}" required maxlength="255">
  @error('email')<div class="error">{{ $message }}</div>@enderror

  <label for="reason">Raison (facultatif)</label>
  <textarea id="reason" name="reason" rows="3" maxlength="2000">{{ old('reason') }}</textarea>

  <label class="check"><input type="checkbox" name="confirm" value="1" required> Je comprends que la suppression est définitive.</label>
  @error('confirm')<div class="error">Merci de cocher cette case.</div>@enderror

  <p style="margin-bottom:0"><button class="btn danger" type="submit">Envoyer la demande</button></p>
</form>

<p class="muted" style="margin-top:20px">Vous pouvez aussi écrire à <a href="mailto:emile.manirambona@zafcpc.org">emile.manirambona@zafcpc.org</a>.</p>
@endsection
