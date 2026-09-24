@extends('emails.layout-consulat')

@section('titre', 'Suivi de votre dossier')

@section('contenu')
    <p>Nous vous informons de l'avancement de votre demande de <strong>{{ $typeLabel }}</strong>, dossier <strong>{{ $demande->numero_dossier }}</strong>.</p>

    @if($demande->statut === 'pret')
        <div class="encadre">
            <strong>Votre dossier est prêt.</strong> Vous pouvez venir le retirer au consulat.
            @if($entite->adresse || $entite->ville_siege)
                <br>{{ collect([$entite->adresse, $entite->ville_siege])->filter()->implode(' - ') }}
            @endif
        </div>
    @elseif($demande->statut === 'rejete')
        <div class="encadre-alerte">
            <strong>Votre dossier n'a pas pu être accepté.</strong>
            @if($demande->motif_rejet)
                <br>Motif : {{ $demande->motif_rejet }}
            @endif
        </div>
        <p>Pour en savoir plus ou déposer une nouvelle demande, contactez le consulat{{ $entite->email ? ' à ' . $entite->email : '' }}.</p>
    @else
        <div class="encadre">
            <strong>Votre dossier est en cours de traitement.</strong>
            @if($demande->date_disponibilite_prevue)
                <br>Disponibilité prévue : {{ $demande->date_disponibilite_prevue->format('d/m/Y') }}
            @endif
        </div>
    @endif

    <p style="text-align: center;">
        <a href="{{ $lien }}" class="bouton">Voir mon dossier</a>
    </p>
@endsection
