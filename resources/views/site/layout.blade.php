<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'DiscipleCoach')</title>
<link rel="icon" href="/images/icon.jpeg">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600&family=Work+Sans:wght@400;500;600&display=swap">
<style>
  :root{--navy:#0B1941;--yellow:#F6B221;--sand:#F3E1C8;--card:#FBF2E2;--taupe:#A89E8D;--rust:#BE5A38;--indigo:#2B3D66;}
  *{box-sizing:border-box}
  body{margin:0;background:var(--sand);color:var(--navy);font:16px/1.65 'Work Sans',system-ui,sans-serif}
  header{background:var(--navy);color:#fff}
  .bar{max-width:860px;margin:0 auto;padding:14px 16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap}
  .bar img{width:36px;height:36px;border-radius:9px}
  .brand{font-family:Fraunces,Georgia,serif;font-weight:600;font-size:20px;color:#fff;text-decoration:none;margin-right:auto}
  nav a{color:var(--sand);text-decoration:none;margin-left:16px;font-size:14px}
  nav a:hover{color:var(--yellow)}
  main{max-width:860px;margin:0 auto;padding:32px 16px 56px}
  h1,h2{font-family:Fraunces,Georgia,serif;line-height:1.2}
  h1{font-size:clamp(28px,5vw,40px);margin:0 0 8px}
  h2{font-size:21px;margin:32px 0 8px}
  a{color:var(--indigo)}
  .muted{color:#5b5a55;font-size:14px}
  .card{background:var(--card);border:1px solid rgba(168,158,141,.5);border-radius:14px;padding:20px}
  .btn{display:inline-block;background:var(--indigo);color:#fff;border:0;border-radius:10px;padding:12px 20px;font:600 15px 'Work Sans',sans-serif;text-decoration:none;cursor:pointer}
  .btn.danger{background:var(--rust)}
  label{display:block;font-weight:600;margin:16px 0 6px}
  input[type=text],input[type=email],textarea{width:100%;padding:11px 12px;border:1px solid var(--taupe);border-radius:10px;font:inherit;background:#fff}
  .check{display:flex;gap:10px;align-items:flex-start;font-weight:400}
  .error{color:var(--rust);font-size:14px;margin-top:4px}
  .ok{background:#e3f1e1;border:1px solid #9cc79a;border-radius:12px;padding:16px;margin-bottom:20px}
  table{width:100%;border-collapse:collapse;font-size:15px}
  td,th{text-align:left;padding:8px;border-bottom:1px solid rgba(168,158,141,.5);vertical-align:top}
  footer{text-align:center;padding:24px 16px;font-size:13px;color:#5b5a55}
</style>
</head>
<body>
<header><div class="bar">
  <img src="/images/icon.jpeg" alt="">
  <a class="brand" href="{{ route('home') }}">DiscipleCoach</a>
  <nav>
    <a href="{{ route('privacy') }}">Confidentialité</a>
    <a href="{{ route('account.delete') }}">Supprimer mon compte</a>
  </nav>
</div></header>
<main>@yield('content')</main>
<footer>© {{ date('Y') }} Campus pour Christ au Burundi · DiscipleCoach</footer>
</body>
</html>
