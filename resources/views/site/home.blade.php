@extends('site.layout')
@section('content')
<h1>DiscipleCoach</h1>
<p>Une application mobile qui aide les coachs à accompagner les disciples dans leur croissance spirituelle, avec des leçons, des quiz et des échanges entre coach et disciple.</p>
<p>Un service de Campus pour Christ au Burundi.</p>
<div class="card">
  <p style="margin-top:0"><a href="{{ route('privacy') }}">Politique de confidentialité</a></p>
  <p style="margin-bottom:0"><a href="{{ route('account.delete') }}">Demander la suppression de mon compte</a></p>
</div>
@endsection
