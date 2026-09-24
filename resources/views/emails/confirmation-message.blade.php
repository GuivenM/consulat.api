<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de message</title>
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
            border-bottom: 3px solid #007bff;
        }
        .logo {
            max-width: 150px;
            margin-bottom: 10px;
        }
        .content {
            padding: 30px 20px;
            background-color: #ffffff;
        }
        .message-box {
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #6c757d;
            background-color: #f8f9fa;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 15px;
        }
        .btn:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $entite->nom }}</h1>
    </div>

    <div class="content">
        <h2>Bonjour {{ $message->prenom }} {{ $message->nom }},</h2>
        
        <p>Nous vous remercions d'avoir contacté le {{ $entite->nom_court ?? $entite->nom }}. Votre message a bien été reçu et sera traité dans les meilleurs délais.</p>

        <div class="message-box">
            <h3>Récapitulatif de votre message :</h3>
            <p><strong>Objet :</strong> 
                @switch($message->objet)
                    @case('question') Question @break
                    @case('partenariat') Demande de partenariat @break
                    @case('service_consulaire') Question sur une démarche @break
                    @case('urgence') Urgence communautaire @break
                    @default Autre
                @endswitch
            </p>
            <p><strong>Message :</strong></p>
            <p>{{ $message->message }}</p>
        </div>

        <p>En attendant notre réponse, vous pouvez consulter les informations et les démarches disponibles sur notre site.</p>

        <a href="{{ $siteUrl }}" class="btn">Visiter notre site</a>

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
        <p>&copy; {{ date('Y') }} {{ $entite->nom_court ?? $entite->nom }}. Tous droits réservés.</p>
    </div>
</body>
</html>