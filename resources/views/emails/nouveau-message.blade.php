{{-- resources/views/emails/nouveau-message.blade.php --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau message de contact</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f4f4f7;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            border-radius: 20px;
            padding: 3px;
        }
        .content {
            background-color: white;
            border-radius: 18px;
            padding: 40px 30px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logo span {
            color: white;
            font-size: 40px;
            font-weight: bold;
        }
        h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        .badge {
            display: inline-block;
            background: #fef3c7;
            color: #d97706;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .objet-badge {
            display: inline-block;
            background: #dbeafe;
            color: #2563eb;
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 10px;
        }
        .info-card {
            background: #f8fafc;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid #e2e8f0;
        }
        .info-row {
            display: flex;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .info-label {
            width: 120px;
            font-weight: 600;
            color: #64748b;
        }
        .info-value {
            flex: 1;
            color: #1e293b;
        }
        .message-box {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #3b82f6;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            font-style: italic;
        }
        .button {
            display: inline-block;
            padding: 15px 30px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: bold;
            margin: 10px 5px;
            transition: transform 0.2s;
        }
        .button:hover {
            transform: translateY(-2px);
        }
        .button-secondary {
            background: white;
            color: #3b82f6;
            border: 2px solid #3b82f6;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
            color: #64748b;
        }
        .highlight {
            background: #fef3c7;
            padding: 2px 8px;
            border-radius: 5px;
            font-weight: bold;
        }
        .urgence {
            color: #dc2626;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="content">
            <div class="header">
                <div class="logo">
                    <span>📬</span>
                </div>
                <h1>✨ Nouveau message de contact</h1>
                <div class="badge">
                    ⏳ En attente de lecture
                </div>
                <div class="objet-badge">
                    {{ $objet }}
                </div>
            </div>

            <p style="font-size: 18px; margin-bottom: 25px; text-align: center;">
                Un nouveau message a été envoyé via le formulaire de contact
            </p>

            <div class="info-card">
                <h3 style="margin-top: 0; margin-bottom: 20px; color: #333;">
                    👤 Informations de l'expéditeur
                </h3>
                
                <div class="info-row">
                    <span class="info-label">Nom complet</span>
                    <span class="info-value"><strong>{{ $prenom }} {{ $nom }}</strong></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value">{{ $email }}</span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Téléphone</span>
                    <span class="info-value">{{ $telephone }}</span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Date d'envoi</span>
                    <span class="info-value">{{ $date }}</span>
                </div>
            </div>

            <div class="message-box">
                <strong style="color: #2563eb;">Message :</strong>
                <p style="margin: 15px 0 0 0; color: #4b5563;">
                    {{ $contenu }}
                </p>
            </div>

            @if(str_contains($objet, 'Urgence'))
            <div style="background: #fee2e2; border-radius: 10px; padding: 15px; margin: 20px 0;">
                <p style="margin: 0; color: #dc2626;">
                    <strong>⚠️ Attention :</strong> Ce message est marqué comme urgent. Veuillez y répondre dans les plus brefs délais.
                </p>
            </div>
            @endif

            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ $dashboardUrl }}" class="button">
                    👁️ Voir le message dans le dashboard
                </a>
                
                <a href="{{ $listeUrl }}" class="button button-secondary">
                    📋 Gérer tous les messages
                </a>
            </div>

            <div style="background: #f0f9ff; border-radius: 10px; padding: 20px; margin: 20px 0;">
                <p style="margin: 0; color: #0369a1;">
                    <strong>ℹ️ Action requise :</strong><br>
                    Ce message n'a pas encore été lu. Connectez-vous au dashboard pour y répondre.
                </p>
            </div>

            <div class="footer">
                <p style="margin: 0;">
                    Cet email a été envoyé automatiquement suite à un nouveau message sur le site.<br>
                    © {{ date('Y') }} {{ $entite->nom_court ?? $entite->nom }} - Tous droits réservés
                </p>
            </div>
        </div>
    </div>
</body>
</html>