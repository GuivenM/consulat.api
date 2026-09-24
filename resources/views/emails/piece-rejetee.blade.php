@extends('emails.layout-consulat')

@section('titre', 'Pièce à redéposer')

@section('contenu')
    <p>Une pièce de votre dossier <strong>{{ $demande->numero_dossier }}</strong> n'a pas pu être validée et doit être redéposée.</p>

    <div class="encadre-alerte">
        <strong>{{ $document->label ?: $document->code_document }}</strong>
        @if($document->motif_rejet)
            <br>Motif : {{ $document->motif_rejet }}
        @endif
    </div>

    <p>Connectez-vous à votre espace consulaire pour déposer une nouvelle version de cette pièce. Votre dossier sera traité dès qu'elle aura été validée.</p>

    <p style="text-align: center;">
        <a href="{{ $lien }}" class="bouton">Redéposer la pièce</a>
    </p>
@endsection
