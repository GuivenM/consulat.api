<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $reinitialisation ? 'Réinitialisation du mot de passe' : 'Vérification de votre email' }}</title>
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
        <h1>{{ $ressortissant->entity->nom }}</h1>
    </div>

    <div class="content">
        <h2>Bonjour {{ $ressortissant->prenom }} {{ $ressortissant->nom }},</h2>

        @if($reinitialisation)
            <p>Vous avez demandé la réinitialisation du mot de passe de votre espace consulaire.</p>

            <p>Pour choisir un nouveau mot de passe, cliquez sur le bouton ci-dessous :</p>

            <p style="text-align: center;">
                <a href="{{ $lien }}" class="bouton">Choisir un nouveau mot de passe</a>
            </p>

            <p>Ce lien est valable 7 jours. Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet email — votre mot de passe actuel reste inchangé.</p>
        @else
            <p><strong>Merci de vous être inscrit(e) au registre consulaire.</strong></p>

            <p>Il ne reste qu'une étape avant d'accéder à votre espace : confirmez votre adresse email en cliquant sur le bouton ci-dessous.</p>

            <p style="text-align: center;">
                <a href="{{ $lien }}" class="bouton">Vérifier mon email</a>
            </p>

            <p>Ce lien est valable 7 jours. Si vous n'êtes pas à l'origine de cette inscription, vous pouvez ignorer cet email.</p>
        @endif

        <p>Cordialement,</p>
        <p><strong>{{ $ressortissant->entity->nom_court ?? $ressortissant->entity->nom }}</strong></p>
    </div>

    <div class="footer">
        <p>{{ $ressortissant->entity->nom }}</p>
        @if($ressortissant->entity->ville_siege)
            <p>{{ $ressortissant->entity->ville_siege }} — {{ $ressortissant->entity->pays_accueil }}</p>
        @endif
        @if($ressortissant->entity->email || $ressortissant->entity->telephone)
            <p>
                @if($ressortissant->entity->email) Email: {{ $ressortissant->entity->email }} @endif
                @if($ressortissant->entity->telephone) | Tél: {{ $ressortissant->entity->telephone }} @endif
            </p>
        @endif
    </div>
</body>
</html>
