<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Votre accès administrateur</title>
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
            border-bottom: 3px solid #3f794b;
        }
        .content {
            padding: 30px 20px;
            background-color: #ffffff;
        }
        .role-badge {
            display: inline-block;
            background-color: #f4f0e6;
            color: #705924;
            font-weight: bold;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 13px;
        }
        .bouton {
            display: inline-block;
            background-color: #3f794b;
            color: #ffffff;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 5px;
            margin: 20px 0;
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
        <h2>Bonjour {{ $user->prenom }} {{ $user->nom }},</h2>

        @if($reinitialisation)
            <p>Vous avez demandé la réinitialisation du mot de passe de votre accès à l'espace d'administration.</p>

            <p>Pour choisir un nouveau mot de passe, cliquez sur le bouton ci-dessous :</p>

            <p style="text-align: center;">
                <a href="{{ $lienActivation }}" class="bouton">Choisir un nouveau mot de passe</a>
            </p>

            <p>Ce lien est valable 7 jours. Si vous n'êtes pas à l'origine de cette demande, contactez immédiatement un administrateur — votre mot de passe actuel reste inchangé tant que vous n'avez pas cliqué.</p>
        @else
            <p>
                Un accès à l'espace d'administration du {{ $entite->nom_court ?? $entite->nom }} vient de vous être créé, avec le rôle
                <span class="role-badge">{{ $user->role_label }}</span>.
            </p>

            <p>Pour l'activer, définissez votre mot de passe en cliquant sur le bouton ci-dessous :</p>

            <p style="text-align: center;">
                <a href="{{ $lienActivation }}" class="bouton">Activer mon accès administrateur</a>
            </p>

            <p>Ce lien est valable 7 jours. Si vous n'êtes pas à l'origine de cette demande, contactez immédiatement un administrateur.</p>
        @endif

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
