<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('titre')</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-bottom: 3px solid #17a2b8;
        }
        .content {
            padding: 30px 20px;
            background-color: #ffffff;
        }
        .bouton {
            display: inline-block;
            background-color: #17a2b8;
            color: #ffffff;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .encadre {
            background-color: #f8f9fa;
            border-left: 4px solid #17a2b8;
            padding: 12px 16px;
            margin: 16px 0;
        }
        .encadre-alerte {
            background-color: #fdf2f2;
            border-left: 4px solid #dc3545;
            padding: 12px 16px;
            margin: 16px 0;
        }
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #6c757d;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $entite->nom }}</h1>
    </div>
    <div class="content">
        <h2>Bonjour {{ $ressortissant->prenom }} {{ $ressortissant->nom }},</h2>
        @yield('contenu')
        <p>Cordialement,</p>
        <p><strong>{{ $entite->nom_court ?? $entite->nom }}</strong></p>
    </div>
    <div class="footer">
        <p>{{ $entite->nom }}</p>
        @if($entite->adresse || $entite->ville_siege)
            <p>{{ collect([$entite->adresse, $entite->ville_siege])->filter()->implode(' - ') }}</p>
        @endif
        @if($entite->email || $entite->telephone)
            <p>{{ collect([$entite->email ? 'Email: ' . $entite->email : null, $entite->telephone ? 'Tél: ' . $entite->telephone : null])->filter()->implode(' | ') }}</p>
        @endif
    </div>
</body>
</html>
